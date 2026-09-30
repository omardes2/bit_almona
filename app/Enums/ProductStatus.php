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

    /**
     * Whether a product with this status is shown in the storefront.
     */
    public function isVisible(): bool
    {
        return $this !== self::Hidden;
    }
}
