<?php

namespace App\Services\Pricing;

final readonly class ResolvedPrice
{
    public function __construct(
        public float $originalPrice,
        public float $finalPrice,
        public ?int $offerId = null,
    ) {}

    public function hasDiscount(): bool
    {
        return $this->finalPrice < $this->originalPrice;
    }
}
