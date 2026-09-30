<?php

namespace App\Enums;

/**
 * The real, time-aware state of a scheduled record (offers, banners),
 * derived from is_active + starts_at + ends_at + now — not from is_active alone.
 */
enum ScheduleStatus: string
{
    case Running = 'running';
    case Scheduled = 'scheduled';
    case Expired = 'expired';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'فعال الآن',
            self::Scheduled => 'مجدول',
            self::Expired => 'منتهي',
            self::Disabled => 'متوقف',
        };
    }

    /** Wording used on the offers page. */
    public function offerLabel(): string
    {
        return $this === self::Scheduled ? 'يبدأ لاحقًا' : $this->label();
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Running => 'bg-green-100 text-green-800',
            self::Scheduled => 'bg-blue-100 text-blue-800',
            self::Expired => 'bg-gray-200 text-gray-700',
            self::Disabled => 'bg-red-100 text-red-700',
        };
    }
}
