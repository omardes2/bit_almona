<?php

namespace App\Actions\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\Orders\OrderPlaced;
use App\Models\Address;
use App\Models\Cart;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Payments\PaymentManager;
use App\Services\Cart\CartService;
use App\Services\Cart\QuantityRules;
use App\Services\Checkout\CheckoutCalculator;
use App\Services\Checkout\CheckoutException;
use App\Support\Money;
use App\Support\Notifications\StockAlerts;
use App\Support\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Turns the customer's cart into an order — atomically.
 *
 * Inside one transaction: lock the cart and the product rows (SELECT ... FOR
 * UPDATE, in id order), re-validate every line against the locked stock,
 * compute totals on the server, create the order + items (snapshots) +
 * payment + status history, deduct stock and empty the cart. Any failure
 * rolls everything back.
 *
 * Idempotent per checkout token: submitting the same checkout twice returns
 * the first order instead of creating a second one.
 */
class PlaceOrder
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CheckoutCalculator $calculator,
        private readonly PaymentManager $payments,
    ) {}

    public function handle(
        User $user,
        string $checkoutToken,
        Address $address,
        int $deliveryZoneId,
        PaymentMethod $paymentMethod = PaymentMethod::CashOnDelivery,
        ?string $notes = null,
        ?int $expectedTotalCents = null,
    ): Order {
        if ($existing = $this->existingOrder($user, $checkoutToken)) {
            return $existing;
        }

        if ($address->user_id !== $user->id) {
            throw new CheckoutException('العنوان المختار غير صالح.');
        }

        $provider = $this->payments->provider($paymentMethod);

        // One checkout at a time per customer (fast double clicks, two tabs).
        $lock = Cache::lock('checkout:user:'.$user->id, 20);

        if (! $lock->get()) {
            throw new CheckoutException('جارٍ معالجة طلبك، يرجى الانتظار لحظات.');
        }

        try {
            if ($existing = $this->existingOrder($user, $checkoutToken)) {
                return $existing;
            }

            return DB::transaction(function () use ($user, $checkoutToken, $address, $deliveryZoneId, $provider, $notes, $expectedTotalCents) {
                // Every read before the product lock is a locking read: with MySQL REPEATABLE READ a
                // plain SELECT here would pin a snapshot taken BEFORE we wait for the stock lock,
                // and the re-validation below would then see stale stock.
                $cart = Cart::query()->where('user_id', $user->id)->lockForUpdate()->first();
                $productIds = $cart ? $cart->items()->orderBy('product_id')->lockForUpdate()->pluck('product_id')->all() : [];

                if ($productIds === []) {
                    throw new CheckoutException('سلتك فارغة.');
                }

                // Lock the stock rows. Concurrent checkouts for the same product wait here,
                // and then see the stock left by the first one — no overselling.
                $locked = Product::withTrashed()->whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                // Validate and price against the LOCKED rows (current stock), never a stale read.
                $summary = $this->cart->summary($cart, $locked);
                $problems = [];

                foreach ($summary->lines as $line) {
                    if ($line->issue !== null) {
                        $problems[] = "«{$line->product->name}»: {$line->issue}";
                    }
                }

                if ($problems !== []) {
                    throw new CheckoutException('بعض المنتجات في سلتك تغيّرت. يرجى مراجعة السلة.', $problems);
                }

                $zone = DeliveryZone::query()->active()->find($deliveryZoneId);

                if ($zone === null) {
                    throw new CheckoutException('منطقة التوصيل المختارة غير متاحة حاليًا.');
                }

                $quote = $this->calculator->quote($summary, $zone);

                if (! $quote->meetsMinimum()) {
                    throw new CheckoutException('الحد الأدنى للطلب في هذه المنطقة هو '.Money::format($quote->minimum()).'. أضف منتجات بقيمة '.Money::format(Money::fromCents($quote->missingForMinimumCents())).' لإكمال الطلب.');
                }

                if ($expectedTotalCents !== null && $expectedTotalCents !== $quote->totalCents()) {
                    throw new CheckoutException('تغيّرت الأسعار أو رسوم التوصيل منذ فتح الصفحة. راجع الإجمالي الجديد ثم أكّد الطلب.');
                }

                $user->loadMissing('customer');

                $order = Order::create([
                    'checkout_token' => $checkoutToken,
                    'user_id' => $user->id,
                    'status' => OrderStatus::New,
                    // Customer + delivery snapshot: later profile/address edits never change this order.
                    'customer_name' => $user->name,
                    'customer_phone' => $user->phone,
                    'customer_whatsapp' => $user->customer?->whatsapp,
                    'recipient_name' => $address->recipient_name ?: $user->name,
                    'recipient_phone' => $address->recipient_phone ?: $user->phone,
                    'delivery_zone_id' => $zone->id,
                    'delivery_zone_name' => $zone->name,
                    'delivery_city' => $address->city,
                    'delivery_area' => $address->area,
                    'address_id' => $address->id,
                    'delivery_address' => trim($address->address_line.($address->notes ? "\n".$address->notes : '')),
                    'customer_notes' => $notes ?: null,
                    'currency' => Store::currencyCode(),
                    'subtotal' => $quote->subtotal(),
                    'discount_total' => $quote->discount(),
                    'delivery_fee' => $quote->delivery(),
                    'total' => $quote->total(),
                    'payment_method' => $provider->method(),
                ]);

                foreach ($summary->lines as $line) {
                    $item = OrderItem::snapshotFrom($line->product, (float) $line->quantity);
                    $item->quantity = $line->quantity;
                    $item->line_total = $line->lineTotal();
                    $order->items()->save($item);

                    // Deduct from the locked row (plain update: no audit noise per order).
                    $product = $locked[$line->product->id];
                    $remaining = QuantityRules::toMilli((string) $product->stock_quantity) - QuantityRules::toMilli($line->quantity);

                    if ($remaining < 0) {
                        // Unreachable after the validation above; never let stock go negative silently.
                        throw new CheckoutException("الكمية المتوفرة من «{$product->name}» لم تعد كافية.");
                    }

                    Product::withTrashed()->whereKey($product->id)->update(['stock_quantity' => QuantityRules::fromMilli($remaining)]);
                    StockAlerts::check($product->id); // alerts are sent only after commit
                }

                $provider->createPayment($order);

                $order->forceFill(['stock_deducted_at' => now()])->save();
                $order->statusHistory()->create([
                    'from_status' => null,
                    'to_status' => OrderStatus::New,
                    'changed_by' => $user->id,
                    'note' => 'تم إنشاء الطلب',
                ]);

                $address->update(['delivery_zone_id' => $zone->id]);
                $cart->delete();

                // Held until COMMIT (ShouldDispatchAfterCommit); dropped on rollback.
                OrderPlaced::dispatch($order, $user);

                return $order;
            });
        } catch (UniqueConstraintViolationException) {
            // A parallel request with the same token won the race.
            return $this->existingOrder($user, $checkoutToken)
                ?? throw new CheckoutException('تعذّر إنشاء الطلب، يرجى المحاولة مرة أخرى.');
        } finally {
            $lock->release();
        }
    }

    private function existingOrder(User $user, string $token): ?Order
    {
        return Order::query()->where('user_id', $user->id)->where('checkout_token', $token)->first();
    }
}
