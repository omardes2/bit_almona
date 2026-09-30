<?php

namespace App\Support;

use App\Models\StoreSetting;

final class Money
{
    /**
     * Format an amount for display to the customer, e.g. "10.00 ₪".
     */
    public static function format(float|int|string|null $amount): string
    {
        $symbol = StoreSetting::get('currency_symbol', config('store.currency.symbol'));

        return number_format((float) $amount, 2).' '.$symbol;
    }
}
