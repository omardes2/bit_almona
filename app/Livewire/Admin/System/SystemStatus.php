<?php

namespace App\Livewire\Admin\System;

use App\Services\System\CheckResult;
use App\Services\System\SchedulerHeartbeat;
use App\Services\System\SystemHealth;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Read-only system status for the super admin. Shows versions, service
 * health and the launch checklist — never credentials or other secrets.
 */
#[Layout('layouts.admin')]
#[Title('حالة النظام')]
class SystemStatus extends Component
{
    public function boot(): void
    {
        Gate::authorize('manage-system');
    }

    public function render(SystemHealth $health, SchedulerHeartbeat $heartbeat)
    {
        $checks = collect($health->checks());

        return view('livewire.admin.system.status', [
            'info' => [
                'إصدار التطبيق' => (string) config('app.version'),
                'البيئة' => (string) config('app.env'),
                'PHP' => PHP_VERSION,
                'Laravel' => Application::VERSION,
                'قاعدة البيانات' => (string) config('database.default'),
                'الكاش' => (string) config('cache.default'),
                'الجلسات' => (string) config('session.driver'),
                'المنطقة الزمنية' => (string) config('app.timezone'),
            ],
            'checks' => $checks,
            'launch' => $health->launchChecklist($checks->all()),
            'criticalCount' => $checks->filter(fn (CheckResult $c) => $c->isCritical())->count(),
            'warningCount' => $checks->where('status', CheckResult::WARNING)->count(),
            'queue' => $health->queueStats(),
            'lastSchedulerRun' => $heartbeat->lastRunAt(),
            'schedulerRunning' => $heartbeat->isRunning(),
        ]);
    }
}
