<?php

namespace App\Models\Concerns;

use App\Enums\ScheduleStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * For models with is_active, starts_at and ends_at columns.
 * An expired record is reported as "expired" even if is_active is still true
 * (or was already switched off by offers:deactivate-expired).
 */
trait HasSchedule
{
    public function scheduleStatus(): ScheduleStatus
    {
        $now = now();

        return match (true) {
            $this->ends_at !== null && $this->ends_at->lte($now) => ScheduleStatus::Expired,
            ! $this->is_active => ScheduleStatus::Disabled,
            $this->starts_at !== null && $this->starts_at->gt($now) => ScheduleStatus::Scheduled,
            default => ScheduleStatus::Running,
        };
    }

    public function isRunning(): bool
    {
        return $this->scheduleStatus() === ScheduleStatus::Running;
    }

    public function scopeRunning(Builder $query): void
    {
        $this->scopeWithScheduleStatus($query, ScheduleStatus::Running);
    }

    public function scopeExpired(Builder $query): void
    {
        $this->scopeWithScheduleStatus($query, ScheduleStatus::Expired);
    }

    /**
     * SQL equivalent of scheduleStatus(), for filtering lists.
     */
    public function scopeWithScheduleStatus(Builder $query, ScheduleStatus $status): void
    {
        $now = now();
        $isActive = $query->qualifyColumn('is_active');
        $startsAt = $query->qualifyColumn('starts_at');
        $endsAt = $query->qualifyColumn('ends_at');

        $notExpired = fn (Builder $q) => $q->whereNull($endsAt)->orWhere($endsAt, '>', $now);

        match ($status) {
            ScheduleStatus::Expired => $query->whereNotNull($endsAt)->where($endsAt, '<=', $now),
            ScheduleStatus::Disabled => $query->where($isActive, false)->where($notExpired),
            ScheduleStatus::Scheduled => $query->where($isActive, true)->where($notExpired)
                ->whereNotNull($startsAt)->where($startsAt, '>', $now),
            ScheduleStatus::Running => $query->where($isActive, true)->where($notExpired)
                ->where(fn (Builder $q) => $q->whereNull($startsAt)->orWhere($startsAt, '<=', $now)),
        };
    }
}
