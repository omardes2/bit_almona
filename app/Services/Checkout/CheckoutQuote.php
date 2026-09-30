<?php

namespace App\Services\Checkout;

use App\Models\DeliveryZone;
use App\Support\Money;

/**
 * Order totals in integer agorot, computed only on the server.
 *
 *   subtotal  = products at their original price
 *   discount  = what offers/markdowns take off
 *   items     = subtotal - discount (what the products cost)
 *   total     = items + delivery
 */
final readonly class CheckoutQuote
{
    public function __construct(
        public int $subtotalCents,
        public int $discountCents,
        public int $deliveryCents,
        public int $minimumCents,
        public ?DeliveryZone $zone,
    ) {}

    public function itemsCents(): int
    {
        return $this->subtotalCents - $this->discountCents;
    }

    public function totalCents(): int
    {
        return $this->itemsCents() + $this->deliveryCents;
    }

    public function meetsMinimum(): bool
    {
        return $this->itemsCents() >= $this->minimumCents;
    }

    public function missingForMinimumCents(): int
    {
        return max(0, $this->minimumCents - $this->itemsCents());
    }

    public function subtotal(): string
    {
        return Money::fromCents($this->subtotalCents);
    }

    public function discount(): string
    {
        return Money::fromCents($this->discountCents);
    }

    public function items(): string
    {
        return Money::fromCents($this->itemsCents());
    }

    public function delivery(): string
    {
        return Money::fromCents($this->deliveryCents);
    }

    public function total(): string
    {
        return Money::fromCents($this->totalCents());
    }

    public function minimum(): string
    {
        return Money::fromCents($this->minimumCents);
    }
}
