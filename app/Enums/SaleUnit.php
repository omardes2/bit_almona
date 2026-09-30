<?php

namespace App\Enums;

enum SaleUnit: string
{
    case Piece = 'piece';
    case Kilogram = 'kg';
    case Gram = 'g';
    case Liter = 'liter';
    case Pack = 'pack';
    case Box = 'box';

    public function label(): string
    {
        return match ($this) {
            self::Piece => 'قطعة',
            self::Kilogram => 'كيلو',
            self::Gram => 'غرام',
            self::Liter => 'لتر',
            self::Pack => 'عبوة',
            self::Box => 'صندوق',
        };
    }

    /**
     * Units that may be ordered in fractional quantities (e.g. 1.5 كيلو).
     */
    public function allowsFractions(): bool
    {
        return in_array($this, [self::Kilogram, self::Liter], true);
    }
}
