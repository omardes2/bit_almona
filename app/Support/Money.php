<?php

namespace App\Support;

final class Money
{
    /**
     * Format an amount for display to the customer, e.g. "10.00 ₪".
     */
    public static function format(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2).' '.Store::currencySymbol();
    }

    /**
     * Shorter storefront format: "18 ₪" for whole amounts, "12.50 ₪" otherwise.
     */
    public static function short(float|int|string|null $amount): string
    {
        $amount = round((float) $amount, 2);
        $decimals = floor($amount) == $amount ? 0 : 2;

        return number_format($amount, $decimals).' '.Store::currencySymbol();
    }

    /** Decimal string ("12.50") to integer agorot, without float drift. */
    public static function toCents(string|int|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
