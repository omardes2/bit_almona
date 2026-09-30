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

    public function isOffer(): bool
    {
        return $this->offerId !== null;
    }

    /**
     * Discount percentage, computed on the server from the resolved prices.
     */
    public function discountPercentage(): int
    {
        if (! $this->hasDiscount() || $this->originalPrice <= 0) {
            return 0;
        }

        return (int) round((1 - $this->finalPrice / $this->originalPrice) * 100);
    }
}
