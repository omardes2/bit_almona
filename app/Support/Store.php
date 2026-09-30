<?php

namespace App\Support;

use App\Models\StoreSetting;
use App\Services\Media\ImageStorage;

/**
 * Read-only access to the store identity stored in store_settings.
 * This is the only place with fallbacks, so the store name is never
 * hard-coded in views.
 */
final class Store
{
    public static function name(): string
    {
        return self::setting('store_name') ?? config('store.name');
    }

    public static function logoUrl(): ?string
    {
        return ImageStorage::url(self::setting('store_logo'));
    }

    public static function phone(): ?string
    {
        return self::setting('store_phone');
    }

    public static function whatsapp(): ?string
    {
        return self::setting('store_whatsapp');
    }

    /**
     * wa.me link for the store WhatsApp number (05XXXXXXXX => 9705XXXXXXXX).
     */
    public static function whatsappUrl(): ?string
    {
        $number = PhoneNumber::normalize(self::whatsapp());

        return PhoneNumber::isValid($number) ? 'https://wa.me/970'.substr($number, 1) : null;
    }

    public static function address(): ?string
    {
        return self::setting('store_address');
    }

    public static function currencyCode(): string
    {
        return self::setting('currency_code') ?? config('store.currency.code');
    }

    public static function currencySymbol(): string
    {
        return self::setting('currency_symbol') ?? config('store.currency.symbol');
    }

    private static function setting(string $key): ?string
    {
        $value = rescue(fn () => StoreSetting::get($key), null, report: false);

        return filled($value) ? (string) $value : null;
    }
}
