<?php

namespace App\Services\Cart;

use App\Models\CartItem;
use App\Models\Product;
use App\Services\Pricing\ResolvedPrice;
use App\Support\Money;

final readonly class CartLine
{
    public function __construct(
        public CartItem $item,
        public Product $product,
        public ResolvedPrice $price,
        public QuantityRules $rules,
        public string $quantity,
        public int $lineTotalCents,
        public bool $purchasable,
        public ?string $issue = null,
    ) {}

    public function lineTotal(): string
    {
        return Money::fromCents($this->lineTotalCents);
    }

    public function canIncrement(): bool
    {
        $current = QuantityRules::toMilli($this->quantity) ?? 0;

        return $this->purchasable && $this->rules->isValid($current + $this->rules->step);
    }

    public function canDecrement(): bool
    {
        $current = QuantityRules::toMilli($this->quantity) ?? 0;

        return $this->purchasable && $current - $this->rules->step >= $this->rules->min;
    }
}
