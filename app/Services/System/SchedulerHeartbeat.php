<?php

namespace App\Services\System;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * The scheduler touches this every minute (system:heartbeat), so the admin
 * panel and store:check-production can tell whether cron is really running.
 */
class SchedulerHeartbeat
{
    public const CACHE_KEY = 'system:last_scheduler_run_at';

    /** No beat for this long means the scheduler (cron) is stopped. */
    public const STALE_AFTER_MINUTES = 5;

    public function beat(): void
    {
        Cache::forever(self::CACHE_KEY, now()->getTimestamp());
    }

    public function lastRunAt(): ?CarbonImmutable
    {
        $timestamp = rescue(fn () => Cache::get(self::CACHE_KEY), null, report: false);

        return is_numeric($timestamp) ? CarbonImmutable::createFromTimestamp((int) $timestamp, config('app.timezone')) : null;
    }

    public function isRunning(): bool
    {
        $last = $this->lastRunAt();

        return $last !== null && $last->greaterThanOrEqualTo(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }
}
