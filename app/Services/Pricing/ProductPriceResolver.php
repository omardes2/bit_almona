<?php

namespace App\Services\Pricing;

use App\Models\Offer;
use App\Models\Product;

/**
 * The single source of truth for what a product costs right now.
 * Carts and orders must always go through this class — prices coming from
 * the browser are never trusted.
 */
class ProductPriceResolver
{
    public function resolve(Product $product): ResolvedPrice
    {
        $originalPrice = (float) $product->original_price;
        $salePrice = (float) $product->sale_price;

        /** @var Offer|null $offer */
        $offer = $product->relationLoaded('activeOffer')
            ? $product->activeOffer
            : $product->activeOffer()->first();

        // Re-check the window in case the relation was eager-loaded earlier.
        if ($offer !== null && $offer->isRunning() && (float) $offer->offer_price < $salePrice) {
            return new ResolvedPrice(
                originalPrice: max($originalPrice, (float) $offer->original_price),
                finalPrice: (float) $offer->offer_price,
                offerId: $offer->id,
            );
        }

        return new ResolvedPrice(
            originalPrice: max($originalPrice, $salePrice),
            finalPrice: $salePrice,
        );
    }
}
