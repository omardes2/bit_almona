<?php

namespace App\Support;

final class Decimal
{
    /**
     * "5.000" => "5", "1.500" => "1.5" — for filling form inputs from DECIMAL columns
     * without going through float.
     */
    public static function trim(string|int|float|null $value): string
    {
        $value = (string) ($value ?? '');

        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return $value === '' || $value === '-0' ? '0' : $value;
    }
}
