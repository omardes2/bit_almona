<?php

namespace App\Services\Cart;

use App\Enums\ProductStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The customer's cart. Guests are identified by a random token kept in the
 * session (stored in carts.session_id); signed-in users by carts.user_id.
 *
 * Only a product id and a quantity ever come from the browser. Status,
 * stock, minimum, step and price are all checked here, on the server.
 */
class CartService
{
    public const SESSION_KEY = 'cart_token';

    public function current(): ?Cart
    {
        if ($user = Auth::user()) {
            return Cart::firstWhere('user_id', $user->id);
        }

        $token = session(self::SESSION_KEY);

        return $token ? Cart::where('session_id', $token)->whereNull('user_id')->first() : null;
    }

    public function currentOrCreate(): Cart
    {
        if ($cart = $this->current()) {
            return $cart;
        }

        if ($user = Auth::user()) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }

        $token = Str::random(40);
        session()->put(self::SESSION_KEY, $token);

        return Cart::create(['session_id' => $token]);
    }

    /** Number of lines in the cart (cheap query for the header badge). */
    public function count(): int
    {
        $cart = $this->current();

        return $cart ? $cart->items()->count() : 0;
    }

    /**
     * Add $quantity (default: the product minimum) of a product. If the
     * product is already in the cart the quantities are added together.
     */
    public function add(int $productId, mixed $quantity = null): CartItem
    {
        $product = $this->purchasableProduct($productId);
        $rules = QuantityRules::forProduct($product);

        $requested = $quantity === null || $quantity === '' ? $rules->min : QuantityRules::toMilli($quantity);

        if ($requested === null || $requested <= 0) {
            throw new CartException('الكمية غير صحيحة.');
        }

        return DB::transaction(function () use ($product, $rules, $requested) {
            $cart = $this->currentOrCreate();
            $item = $cart->items()->where('product_id', $product->id)->lockForUpdate()->first();
            $existing = $item ? (QuantityRules::toMilli($item->quantity) ?? 0) : 0;

            $this->assertValidQuantity($product, $rules, $existing + $requested, adding: $existing > 0);

            $item ??= $cart->items()->make(['product_id' => $product->id]);
            $item->quantity = QuantityRules::fromMilli($existing + $requested);
            $item->unit_price_at_add = $product->price()->finalPrice;
            $item->save();
            $cart->touch();

            return $item;
        });
    }

    /**
     * Set the quantity of a cart line (absolute value).
     */
    public function updateQuantity(int $itemId, mixed $quantity): CartItem
    {
        $item = $this->ownItem($itemId);
        $product = $this->purchasableProduct($item->product_id);
        $rules = QuantityRules::forProduct($product);
        $milli = QuantityRules::toMilli($quantity);

        if ($milli === null || $milli <= 0) {
            throw new CartException('الكمية غير صحيحة.');
        }

        $this->assertValidQuantity($product, $rules, $milli);

        $item->update(['quantity' => QuantityRules::fromMilli($milli)]);
        $item->cart->touch();

        return $item;
    }

    public function increment(int $itemId): CartItem
    {
        $item = $this->ownItem($itemId);
        $rules = QuantityRules::forProduct($this->purchasableProduct($item->product_id));

        return $this->updateQuantity($itemId, QuantityRules::fromMilli((QuantityRules::toMilli($item->quantity) ?? 0) + $rules->step));
    }

    public function decrement(int $itemId): CartItem
    {
        $item = $this->ownItem($itemId);
        $rules = QuantityRules::forProduct($this->purchasableProduct($item->product_id));
        $next = (QuantityRules::toMilli($item->quantity) ?? 0) - $rules->step;

        if ($next < $rules->min) {
            throw new CartException('هذه أقل كمية ممكنة لهذا المنتج. يمكنك حذفه من السلة.');
        }

        return $this->updateQuantity($itemId, QuantityRules::fromMilli($next));
    }

    public function remove(int $itemId): void
    {
        $item = $this->ownItem($itemId);
        $item->delete();
        $item->cart->touch();
    }

    /**
     * All lines with their current server-side price. Lines that can no
     * longer be bought stay visible (with a reason) but are not counted.
     */
    public function summary(?Cart $cart = null): CartSummary
    {
        $cart ??= $this->current();

        if ($cart === null) {
            return CartSummary::empty();
        }

        $items = $cart->items()->with(['product.category:id,is_active', 'product.activeOffer'])->get();
        $lines = [];
        $subtotal = 0;

        foreach ($items as $item) {
            $product = $item->product;
            $rules = QuantityRules::forProduct($product);
            $quantity = QuantityRules::toMilli($item->quantity) ?? 0;
            $issue = $this->issueFor($product, $rules, $quantity);
            $price = $product->price();
            $lineTotal = (int) round(Money::toCents($price->finalPrice) * $quantity / 1000);

            $lines[] = new CartLine(
                item: $item,
                product: $product,
                price: $price,
                rules: $rules,
                quantity: QuantityRules::fromMilli($quantity),
                lineTotalCents: $lineTotal,
                purchasable: $issue === null,
                issue: $issue,
            );

            if ($issue === null) {
                $subtotal += $lineTotal;
            }
        }

        return new CartSummary($lines, $subtotal);
    }

    /**
     * Bring the cart in line with the current catalog: quantities above the
     * stock are reduced, and price changes since the item was added are
     * reported. Returns customer-facing notices.
     *
     * @return list<string>
     */
    public function refresh(): array
    {
        $cart = $this->current();

        if ($cart === null) {
            return [];
        }

        $notices = [];

        foreach ($this->summary($cart)->lines as $line) {
            $product = $line->product;
            $quantity = QuantityRules::toMilli($line->quantity) ?? 0;

            if ($this->isBuyable($product) && ! $line->rules->isValid($quantity) && ($clamped = $line->rules->clamp($quantity)) !== null) {
                $line->item->update(['quantity' => QuantityRules::fromMilli($clamped)]);
                $notices[] = "تم تعديل كمية «{$product->name}» إلى ".QuantityRules::fromMilli($clamped).' حسب المخزون المتوفر.';
            }

            $old = $line->item->unit_price_at_add;
            $new = number_format($line->price->finalPrice, 2, '.', '');

            if ($old !== null && $old !== $new && $this->isBuyable($product)) {
                $direction = (float) $new < (float) $old ? 'انخفض' : 'تغيّر';
                $notices[] = "{$direction} سعر «{$product->name}» من ".Money::short($old).' إلى '.Money::short($new).'.';
                $line->item->update(['unit_price_at_add' => $new]);
            }
        }

        return $notices;
    }

    /**
     * Move the guest cart into the user's cart after login. Same products
     * are combined into one line; the combined quantity is reduced to the
     * largest valid quantity (stock, minimum and step). Products that can no
     * longer be bought are dropped.
     */
    public function mergeGuestCart(User $user, ?string $token): void
    {
        if (blank($token)) {
            return;
        }

        $guestCart = Cart::where('session_id', $token)->whereNull('user_id')->first();

        if ($guestCart === null) {
            return;
        }

        DB::transaction(function () use ($user, $guestCart) {
            $userCart = Cart::firstOrCreate(['user_id' => $user->id]);
            $existing = $userCart->items()->get()->keyBy('product_id');

            foreach ($guestCart->items()->with(['product.category:id,is_active', 'product.activeOffer'])->get() as $guestItem) {
                $product = $guestItem->product;

                if (! $this->isBuyable($product)) {
                    continue;
                }

                $rules = QuantityRules::forProduct($product);
                $userItem = $existing->get($product->id);
                $combined = (QuantityRules::toMilli($guestItem->quantity) ?? 0)
                    + ($userItem ? (QuantityRules::toMilli($userItem->quantity) ?? 0) : 0);

                $quantity = $rules->clamp($combined);

                if ($quantity === null) {
                    continue;
                }

                $userCart->items()->updateOrCreate(
                    ['product_id' => $product->id],
                    [
                        'quantity' => QuantityRules::fromMilli($quantity),
                        'unit_price_at_add' => $userItem?->unit_price_at_add ?? $guestItem->unit_price_at_add,
                    ],
                );
            }

            $guestCart->delete();
            $userCart->touch();
        });
    }

    private function purchasableProduct(int $productId): Product
    {
        $product = Product::query()->storefront()->with('activeOffer')->find($productId);

        if ($product === null) {
            throw new CartException('هذا المنتج غير متاح.');
        }

        if ($product->status !== ProductStatus::Available) {
            throw new CartException("«{$product->name}» غير متوفر حاليًا.");
        }

        $rules = QuantityRules::forProduct($product);

        if ($rules->maxAllowed() === null) {
            throw new CartException("نفدت كمية «{$product->name}» من المخزون.");
        }

        return $product;
    }

    private function assertValidQuantity(Product $product, QuantityRules $rules, int $quantity, bool $adding = false): void
    {
        $unit = $product->unit->label();

        if ($quantity > $rules->max) {
            $available = QuantityRules::fromMilli($rules->maxAllowed() ?? 0);

            throw new CartException($adding
                ? "لا يمكن إضافة المزيد من «{$product->name}». المتوفر: {$available} {$unit}."
                : "الكمية المتوفرة من «{$product->name}» هي {$available} {$unit} فقط.");
        }

        if ($quantity < $rules->min) {
            throw new CartException('أقل كمية للطلب هي '.QuantityRules::fromMilli($rules->min)." {$unit}.");
        }

        if (($quantity - $rules->min) % $rules->step !== 0) {
            throw new CartException('الكمية يجب أن تزيد بمقدار '.QuantityRules::fromMilli($rules->step)." {$unit}.");
        }
    }

    private function ownItem(int $itemId): CartItem
    {
        $cart = $this->current();
        $item = $cart?->items()->whereKey($itemId)->first();

        if ($item === null) {
            throw new CartException('هذا المنتج لم يعد في سلتك.');
        }

        return $item->setRelation('cart', $cart);
    }

    private function isBuyable(?Product $product): bool
    {
        return $product !== null
            && $product->isVisibleInStore()
            && $product->status === ProductStatus::Available
            && QuantityRules::forProduct($product)->maxAllowed() !== null;
    }

    private function issueFor(Product $product, QuantityRules $rules, int $quantity): ?string
    {
        if (! $product->isVisibleInStore()) {
            return 'هذا المنتج لم يعد متاحًا. احذفه من السلة.';
        }

        if ($product->status !== ProductStatus::Available) {
            return 'غير متوفر حاليًا.';
        }

        if ($rules->maxAllowed() === null) {
            return 'نفد من المخزون.';
        }

        if (! $rules->isValid($quantity)) {
            return 'الكمية المتوفرة أقل من المطلوب. المتوفر: '.QuantityRules::fromMilli($rules->maxAllowed()).'.';
        }

        return null;
    }
}
