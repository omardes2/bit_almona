<?php

namespace App\Livewire\Store;

use App\Actions\Checkout\PlaceOrder;
use App\Enums\PaymentMethod;
use App\Livewire\Forms\AddressForm;
use App\Models\Address;
use App\Models\DeliveryZone;
use App\Payments\PaymentException;
use App\Payments\PaymentManager;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutCalculator;
use App\Services\Checkout\CheckoutException;
use App\Support\PhoneNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * One-page checkout. The browser only chooses things (address, zone, notes,
 * payment method); every amount is calculated on the server by
 * CheckoutCalculator / PlaceOrder.
 */
#[Layout('layouts.app', ['noindex' => true])]
#[Title('إتمام الطلب')]
class Checkout extends Component
{
    public const UNEXPECTED_ERROR = 'حدث خطأ غير متوقع، حاول مرة أخرى';

    /** Idempotency key: one checkout page load can create at most one order. */
    #[Locked]
    public string $checkoutToken = '';

    /** The total the customer was shown last; the order is refused if it changed. */
    #[Locked]
    public ?int $shownTotalCents = null;

    public int|string|null $addressId = null;

    public bool $useNewAddress = false;

    public AddressForm $newAddress;

    public int|string|null $zoneId = null;

    public string $notes = '';

    public string $whatsapp = '';

    public string $paymentMethod = '';

    /** @var list<string> */
    public array $problems = [];

    public ?string $error = null;

    public function mount(CartService $cart, PaymentManager $payments)
    {
        if ($cart->summary()->purchasableCount() === 0) {
            session()->flash('toast', ['message' => 'سلتك فارغة أو لا تحتوي منتجات متوفرة.', 'type' => 'error']);

            return $this->redirectRoute('cart', navigate: true);
        }

        $user = Auth::user()->loadMissing('customer');
        $this->checkoutToken = Str::random(40);
        $this->paymentMethod = ($payments->enabledMethods()[0] ?? null)?->value ?? '';
        $this->whatsapp = (string) ($user->customer?->whatsapp ?? $user->phone);
        $this->newAddress->forUser($user);

        $default = $user->addresses()->orderByDesc('is_default')->latest('id')->first();

        if ($default) {
            $this->addressId = $default->id;
            $this->zoneId = DeliveryZone::query()->active()->whereKey($default->delivery_zone_id)->value('id');
        } else {
            $this->useNewAddress = true;
        }
    }

    public function updatedAddressId(): void
    {
        $address = $this->ownAddress();

        if ($address?->delivery_zone_id && DeliveryZone::query()->active()->whereKey($address->delivery_zone_id)->exists()) {
            $this->zoneId = $address->delivery_zone_id;
        }
    }

    public function placeOrder(PlaceOrder $placeOrder, PaymentManager $payments, CartService $cart)
    {
        $this->reset('problems', 'error');
        $user = Auth::user();

        $key = 'checkout-submit:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 6)) {
            $this->error = 'محاولات كثيرة. يرجى الانتظار دقيقة ثم المحاولة مرة أخرى.';

            return null;
        }

        RateLimiter::hit($key, 60);

        $this->whatsapp = $this->whatsapp !== '' ? PhoneNumber::normalize($this->whatsapp) : '';

        $data = $this->validate([
            'zoneId' => ['required', 'integer', Rule::exists(DeliveryZone::class, 'id')->where('is_active', true)],
            'addressId' => [Rule::requiredIf(! $this->useNewAddress), 'nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'whatsapp' => ['nullable', 'regex:'.PhoneNumber::PATTERN],
            'paymentMethod' => ['required', Rule::in(array_map(fn ($m) => $m->value, $payments->enabledMethods()))],
        ], [
            'zoneId.required' => 'اختر منطقة التوصيل.',
            'zoneId.exists' => 'منطقة التوصيل المختارة غير متاحة حاليًا.',
            'addressId.required' => 'اختر عنوان التوصيل.',
            'whatsapp.regex' => 'رقم الواتساب يجب أن يكون بالصيغة 05XXXXXXXX.',
        ], ['notes' => 'ملاحظات الطلب']);

        // Keep the customer's WhatsApp number up to date (the login phone never changes here).
        if ($data['whatsapp'] && $data['whatsapp'] !== $user->customer?->whatsapp) {
            $user->customer()->updateOrCreate([], ['whatsapp' => $data['whatsapp']]);
        }

        if ($this->useNewAddress) {
            $address = $this->newAddress->save($user);
            $this->addressId = $address->id;
            $this->useNewAddress = false;
        } else {
            $address = $this->ownAddress() ?? abort(403);
            $this->authorize('view', $address);
        }

        try {
            $order = $placeOrder->handle(
                user: $user->fresh(),
                checkoutToken: $this->checkoutToken,
                address: $address,
                deliveryZoneId: (int) $data['zoneId'],
                paymentMethod: PaymentMethod::from($data['paymentMethod']),
                notes: trim($data['notes']) ?: null,
                expectedTotalCents: $this->shownTotalCents,
            );
        } catch (CheckoutException $e) {
            $this->error = $e->getMessage();
            $this->problems = $e->problems;

            return null;
        } catch (PaymentException) {
            $this->addError('paymentMethod', 'طريقة الدفع المختارة غير متاحة حاليًا.');

            return null;
        } catch (Throwable $e) {
            // Nothing was saved (PlaceOrder is one transaction). Log safe context only:
            // no address, phone, notes or SQL bindings.
            Log::error('checkout.unexpected_error', [
                'user_id' => $user->id,
                'checkout_ref' => substr(hash('sha256', $this->checkoutToken), 0, 12),
                'zone_id' => (int) $data['zoneId'],
                'payment_method' => $data['paymentMethod'],
                'error' => $e::class,
                'at' => basename($e->getFile()).':'.$e->getLine(),
                'detail' => $e instanceof QueryException ? 'SQLSTATE '.$e->getCode() : Str::limit($e->getMessage(), 200),
            ]);

            $this->error = self::UNEXPECTED_ERROR;

            return null;
        }

        $this->dispatch('cart-updated', count: $cart->count());

        // Online gateways (future) send the customer to their hosted payment page.
        if ($paymentUrl = $payments->redirectUrlFor($order)) {
            return $this->redirect($paymentUrl);
        }

        return $this->redirectRoute('order.confirmed', $order, navigate: true);
    }

    private function ownAddress(): ?Address
    {
        return $this->addressId ? Auth::user()->addresses()->whereKey((int) $this->addressId)->first() : null;
    }

    public function render(CartService $cart, CheckoutCalculator $calculator, PaymentManager $payments)
    {
        $summary = $cart->summary();
        $zones = DeliveryZone::query()->active()->ordered()->get();
        $zone = $zones->firstWhere('id', (int) $this->zoneId);
        $quote = $calculator->quote($summary, $zone);

        // Remember what the customer is looking at (only meaningful once a zone is chosen).
        $this->shownTotalCents = $zone ? $quote->totalCents() : null;

        return view('livewire.store.checkout', [
            'summary' => $summary,
            'zones' => $zones,
            'quote' => $quote,
            'addresses' => Auth::user()->addresses()->orderByDesc('is_default')->latest('id')->get(),
            'methods' => $payments->checkoutOptions(),
            'user' => Auth::user(),
        ]);
    }
}
