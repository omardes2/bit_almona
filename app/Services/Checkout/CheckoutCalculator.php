<?php

namespace App\Services\Checkout;

use App\Models\DeliveryZone;
use App\Models\StoreSetting;
use App\Services\Cart\CartSummary;
use App\Services\Cart\QuantityRules;
use App\Support\Money;

/**
 * The one place that turns a cart into totals. Used for the summary shown on
 * the checkout page AND when the order is created, so both always agree.
 * Line prices come from CartService (i.e. ProductPriceResolver); the delivery
 * fee and minimum come from the database — never from the browser.
 */
class CheckoutCalculator
{
    public function quote(CartSummary $summary, ?DeliveryZone $zone): CheckoutQuote
    {
        $subtotal = 0;
        $discount = 0;

        foreach ($summary->lines as $line) {
            if (! $line->purchasable) {
                continue;
            }

            $quantity = QuantityRules::toMilli($line->quantity) ?? 0;
            $original = (int) round(Money::toCents($line->price->originalPrice) * $quantity / 1000);

            $subtotal += $original;
            $discount += max(0, $original - $line->lineTotalCents);
        }

        return new CheckoutQuote(
            subtotalCents: $subtotal,
            discountCents: $discount,
            deliveryCents: $zone ? Money::toCents($zone->delivery_fee) : 0,
            minimumCents: $this->minimumCents($zone),
            zone: $zone,
        );
    }

    /**
     * The stricter of the store-wide minimum and the zone minimum.
     */
    public function minimumCents(?DeliveryZone $zone): int
    {
        $store = Money::toCents((string) (StoreSetting::get('min_order_amount') ?? 0));
        $zoneMinimum = $zone && $zone->min_order_amount !== null ? Money::toCents($zone->min_order_amount) : 0;

        return max($store, $zoneMinimum);
    }
}
