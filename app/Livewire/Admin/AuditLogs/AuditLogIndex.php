<?php

namespace App\Livewire\Admin\AuditLogs;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('سجل العمليات')]
class AuditLogIndex extends Component
{
    use WithPagination;

    public const TYPES = [
        'product' => 'منتج', 'category' => 'قسم', 'offer' => 'عرض', 'banner' => 'بنر',
        'delivery_zone' => 'منطقة توصيل', 'store_setting' => 'إعداد', 'order' => 'طلب',
        'payment' => 'دفعة', 'user' => 'مستخدم',
    ];

    public const EVENTS = [
        'created' => 'إنشاء', 'updated' => 'تعديل', 'deleted' => 'حذف', 'restored' => 'استعادة',
        'force_deleted' => 'حذف نهائي', 'status_changed' => 'تغيير حالة',
        'payment_status_changed' => 'تغيير حالة الدفع', 'export' => 'تصدير',
    ];

    #[Url(except: '')]
    public string $user = '';

    #[Url(except: '')]
    public string $event = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $date = '';

    public ?int $expanded = null;

    public function boot(): void
    {
        Gate::authorize('view-audit-logs');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['user', 'event', 'type', 'date'], true)) {
            $this->resetPage();
        }
    }

    public function toggle(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    public function render()
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) ? Carbon::createFromFormat('Y-m-d', $this->date) : null;

        $logs = AuditLog::query()
            ->with('user:id,name,type')
            ->when($this->user !== '', fn ($q) => $q->where('user_id', (int) $this->user))
            ->when(array_key_exists($this->event, self::EVENTS), fn ($q) => $q->where('event', $this->event))
            ->when(array_key_exists($this->type, self::TYPES), fn ($q) => $q->where('auditable_type', $this->type))
            ->when($day, fn ($q) => $q->whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]))
            ->latest('id')
            ->paginate(25);

        return view('livewire.admin.audit-logs.index', [
            'logs' => $logs,
            'admins' => User::query()->admins()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
