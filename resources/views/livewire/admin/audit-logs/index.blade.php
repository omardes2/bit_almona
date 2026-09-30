@php($types = \App\Livewire\Admin\AuditLogs\AuditLogIndex::TYPES)
@php($events = \App\Livewire\Admin\AuditLogs\AuditLogIndex::EVENTS)
<div>
    <x-admin.page-header title="سجل العمليات" subtitle="من غيّر ماذا ومتى" />

    <div class="mb-4 grid grid-cols-2 gap-2 lg:grid-cols-4">
        <select wire:model.live="user" class="form-input" aria-label="المستخدم">
            <option value="">كل المستخدمين</option>
            @foreach ($admins as $admin)<option value="{{ $admin->id }}">{{ $admin->name }}</option>@endforeach
        </select>
        <select wire:model.live="event" class="form-input" aria-label="العملية">
            <option value="">كل العمليات</option>
            @foreach ($events as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        </select>
        <select wire:model.live="type" class="form-input" aria-label="النوع">
            <option value="">كل الأنواع</option>
            @foreach ($types as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        </select>
        <input type="date" wire:model.live="date" class="form-input" aria-label="التاريخ">
    </div>

    <div wire:loading.class="opacity-60" class="card divide-y divide-gray-100">
        @forelse ($logs as $log)
            <div wire:key="log-{{ $log->id }}">
                <button type="button" wire:click="toggle({{ $log->id }})" class="flex w-full flex-wrap items-center gap-x-3 gap-y-1 p-3 text-start text-sm hover:bg-gray-50" :aria-expanded="{{ $expanded === $log->id ? 'true' : 'false' }}">
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-bold">{{ $events[$log->event] ?? $log->event }}</span>
                    <span class="font-medium">{{ $types[$log->auditable_type] ?? $log->auditable_type }} #{{ $log->auditable_id }}</span>
                    <span class="text-gray-600">{{ $log->user?->name ?? 'النظام / زبون' }}</span>
                    <span class="ms-auto text-xs text-gray-500"><bdi dir="ltr">{{ $log->created_at?->format('Y-m-d H:i') }}</bdi> · <bdi dir="ltr">{{ $log->ip_address ?? '—' }}</bdi></span>
                </button>
                @if ($expanded === $log->id)
                    <div class="grid gap-3 bg-gray-50 p-3 text-xs sm:grid-cols-2">
                        @foreach (['القيم السابقة' => $log->old_values, 'القيم الجديدة' => $log->new_values] as $title => $values)
                            <div class="min-w-0">
                                <div class="mb-1 font-bold">{{ $title }}</div>
                                @if ($values)
                                    <dl class="space-y-0.5 overflow-x-auto rounded-lg bg-white p-2 ring-1 ring-gray-200">
                                        @foreach (\App\Models\AuditLog::redact($values) as $key => $value)
                                            <div class="flex gap-2"><dt class="shrink-0 text-gray-500" dir="ltr">{{ $key }}</dt><dd class="min-w-0 break-words" dir="auto">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (is_bool($value) ? ($value ? 'true' : 'false') : $value) }}</dd></div>
                                        @endforeach
                                    </dl>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p class="p-8 text-center text-sm text-gray-500">لا توجد عمليات مطابقة.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $logs->links('components.admin.pagination') }}</div>
</div>
