<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديد',
            self::Confirmed => 'تم التأكيد',
            self::Preparing => 'قيد التجهيز',
            self::OutForDelivery => 'خرج للتوصيل',
            self::Delivered => 'تم التسليم',
            self::Cancelled => 'ملغي',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::New => 'bg-blue-100 text-blue-800',
            self::Confirmed => 'bg-indigo-100 text-indigo-800',
            self::Preparing => 'bg-amber-100 text-amber-800',
            self::OutForDelivery => 'bg-purple-100 text-purple-800',
            self::Delivered => 'bg-green-100 text-green-800',
            self::Cancelled => 'bg-red-100 text-red-700',
        };
    }

    /** Button label for moving an order INTO this status (admin). */
    public function actionLabel(): string
    {
        return match ($this) {
            self::New => 'إعادة كجديد',
            self::Confirmed => 'تأكيد الطلب',
            self::Preparing => 'بدء التجهيز',
            self::OutForDelivery => 'خرج للتوصيل',
            self::Delivered => 'تم التسليم',
            self::Cancelled => 'إلغاء الطلب',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Preparing, self::Cancelled],
            self::Preparing => [self::OutForDelivery, self::Cancelled],
            self::OutForDelivery => [self::Delivered, self::Cancelled],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * The orders column that records when this status was reached.
     */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::New => null,
            self::Confirmed => 'confirmed_at',
            self::Preparing => 'preparing_at',
            self::OutForDelivery => 'out_for_delivery_at',
            self::Delivered => 'delivered_at',
            self::Cancelled => 'cancelled_at',
        };
    }
}
