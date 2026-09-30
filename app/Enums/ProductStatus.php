<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'متوفر',
            self::Unavailable => 'غير متوفر',
            self::Hidden => 'مخفي',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Available => 'bg-green-100 text-green-800',
            self::Unavailable => 'bg-amber-100 text-amber-800',
            self::Hidden => 'bg-gray-200 text-gray-700',
        };
    }

    /**
     * Whether a product with this status is shown in the storefront.
     */
    public function isVisible(): bool
    {
        return $this !== self::Hidden;
    }
}
