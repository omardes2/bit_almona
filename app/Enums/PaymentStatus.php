<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار الدفع',
            self::Paid => 'مدفوع',
            self::Failed => 'فشل الدفع',
            self::Refunded => 'مسترجع',
            self::Cancelled => 'ملغي',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800',
            self::Paid => 'bg-green-100 text-green-800',
            self::Failed, self::Cancelled => 'bg-red-100 text-red-700',
            self::Refunded => 'bg-gray-200 text-gray-700',
        };
    }
}
