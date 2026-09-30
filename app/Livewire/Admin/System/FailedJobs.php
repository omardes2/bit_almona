<?php

namespace App\Livewire\Admin\System;

use App\Livewire\Admin\Concerns\Toasts;
use App\Logging\RedactSensitiveData;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Failed queue jobs (super admin). Only the job class, queue, time and the
 * first line of the error are shown — never the serialized payload, which
 * can contain customer data.
 */
#[Layout('layouts.admin')]
#[Title('المهام الفاشلة')]
class FailedJobs extends Component
{
    use Toasts, WithPagination;

    public function boot(): void
    {
        Gate::authorize('manage-system');
    }

    public function retry(string $uuid): void
    {
        if (! $this->exists($uuid)) {
            return;
        }

        Artisan::call('queue:retry', ['id' => [$uuid]]);
        Log::info('queue.failed_job_retried', ['uuid' => $uuid, 'by' => auth()->id()]);
        $this->toast('أُعيدت المهمة إلى الطابور.');
    }

    public function forget(string $uuid): void
    {
        if (! $this->exists($uuid)) {
            return;
        }

        app('queue.failer')->forget($uuid);
        Log::info('queue.failed_job_deleted', ['uuid' => $uuid, 'by' => auth()->id()]);
        $this->toast('تم حذف المهمة.');
    }

    public function retryAll(): void
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
        Log::info('queue.failed_jobs_retried_all', ['by' => auth()->id()]);
        $this->toast('أُعيدت كل المهام الفاشلة إلى الطابور.');
    }

    private function exists(string $uuid): bool
    {
        return Str::isUuid($uuid) && DB::table('failed_jobs')->where('uuid', $uuid)->exists();
    }

    /**
     * @return array{name: string, error: string}
     */
    public static function summarize(object $row): array
    {
        $payload = json_decode((string) $row->payload, true);
        $name = is_array($payload) ? (string) ($payload['displayName'] ?? 'غير معروف') : 'غير معروف';
        $firstLine = Str::before((string) $row->exception, "\n");

        return [
            'name' => class_basename($name),
            'error' => Str::limit(RedactSensitiveData::redactString($firstLine), 240),
        ];
    }

    public function render()
    {
        $jobs = DB::table('failed_jobs')
            ->select(['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.admin.system.failed-jobs', [
            'jobs' => $jobs->through(fn ($row) => (object) [
                'uuid' => $row->uuid,
                'queue' => $row->queue,
                'connection' => $row->connection,
                'failed_at' => $row->failed_at,
                ...self::summarize($row),
            ]),
        ]);
    }
}
