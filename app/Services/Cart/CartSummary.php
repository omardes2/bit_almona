<?php

namespace App\Services\Cart;

use App\Support\Money;

final readonly class CartSummary
{
    /**
     * @param  list<CartLine>  $lines
     */
    public function __construct(
        public array $lines,
        public int $subtotalCents,
    ) {}

    public static function empty(): self
    {
        return new self([], 0);
    }

    public function subtotal(): string
    {
        return Money::fromCents($this->subtotalCents);
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function count(): int
    {
        return count($this->lines);
    }

    public function purchasableCount(): int
    {
        return count(array_filter($this->lines, fn (CartLine $line) => $line->purchasable));
    }

    public function hasIssues(): bool
    {
        return array_filter($this->lines, fn (CartLine $line) => $line->issue !== null) !== [];
    }
}
