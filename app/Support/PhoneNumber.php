<?php

namespace App\Support;

/**
 * Normalises Palestinian mobile numbers (Jawwal / Ooredoo and the 05x
 * numbers also used in Hebron) to the local 10-digit format: 05XXXXXXXX.
 */
final class PhoneNumber
{
    public const PATTERN = '/^05\d{8}$/';

    public static function normalize(?string $phone): string
    {
        if ($phone === null) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', self::westernDigits($phone)) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        foreach (['970', '972'] as $countryCode) {
            if (str_starts_with($digits, $countryCode) && strlen($digits) === 12) {
                return '0'.substr($digits, 3);
            }
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            return '0'.$digits;
        }

        return $digits;
    }

    /** Arabic-Indic (٠-٩) and Eastern Arabic-Indic (۰-۹) digits => 0-9. */
    public static function westernDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    /** 0599123456 => 059•••••56 (for "we sent a code to ..." messages). */
    public static function mask(?string $phone): string
    {
        $phone = self::normalize($phone);

        return strlen($phone) < 6 ? '•••' : substr($phone, 0, 3).str_repeat('•', strlen($phone) - 5).substr($phone, -2);
    }

    public static function isValid(?string $phone): bool
    {
        return (bool) preg_match(self::PATTERN, self::normalize($phone));
    }
}
