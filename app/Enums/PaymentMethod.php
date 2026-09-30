<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CashOnDelivery = 'cash_on_delivery';

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'الدفع عند الاستلام',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'ادفع نقدًا عند استلام طلبك.',
        };
    }
}
