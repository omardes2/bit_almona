@php
    $styles = [
        'ok' => ['bg-green-100 text-green-800', 'سليم', 'check'],
        'warning' => ['bg-amber-100 text-amber-800', 'تحذير', 'alert'],
        'critical' => ['bg-red-100 text-red-700', 'حرج', 'alert'],
    ];
    $launchReady = collect($launch)->every(fn ($c) => ! $c->isCritical());
@endphp
<div class="space-y-4">
    <x-admin.page-header title="حالة النظام" subtitle="معلومات تشغيلية للمدير العام فقط — لا تُعرض أي كلمات مرور أو مفاتيح">
        <x-slot:actions>
            <a href="{{ route('admin.system.failed-jobs') }}" wire:navigate class="btn-secondary">المهام الفاشلة</a>
            <a href="{{ route('admin.integrations') }}" wire:navigate class="btn-secondary">التكاملات</a>
            <button type="button" wire:click="$refresh" class="btn-secondary" wire:loading.attr="disabled">تحديث</button>
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Launch checklist --}}
    <section class="card p-4 sm:p-6" aria-labelledby="launch-title">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 id="launch-title" class="text-lg font-bold">جاهزية الإطلاق</h2>
            <span @class(['rounded-full px-3 py-1 text-sm font-bold', 'bg-green-100 text-green-800' => $launchReady, 'bg-red-100 text-red-700' => ! $launchReady])>
                {{ $launchReady ? 'جاهز للإطلاق' : 'غير جاهز بعد' }}
            </span>
        </div>
        <ul class="grid gap-2 sm:grid-cols-2">
            @foreach ($launch as $item)
                <li wire:key="launch-{{ $item->key }}" class="flex items-start gap-2 rounded-xl border border-gray-100 p-3 text-sm">
                    <span @class(['mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full', $styles[$item->status][0]])>
                        <x-icon :name="$styles[$item->status][2]" class="size-4" />
                    </span>
                    <span class="min-w-0">
                        <span class="block font-bold">{{ $item->label }}</span>
                        <span class="block text-gray-600">{{ $item->message }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Versions --}}
        <section class="card p-4" aria-labelledby="info-title">
            <h2 id="info-title" class="mb-3 font-bold">معلومات التشغيل</h2>
            <dl class="space-y-2 text-sm">
                @foreach ($info as $label => $value)
                    <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ $label }}</dt><dd class="font-medium"><bdi dir="ltr">{{ $value }}</bdi></dd></div>
                @endforeach
            </dl>
        </section>

        {{-- Scheduler --}}
        <section class="card p-4" aria-labelledby="scheduler-title">
            <h2 id="scheduler-title" class="mb-3 font-bold">المُجدول (cron)</h2>
            <p @class(['inline-flex rounded-full px-3 py-1 text-sm font-bold', 'bg-green-100 text-green-800' => $schedulerRunning, 'bg-red-100 text-red-700' => ! $schedulerRunning])>
                {{ $schedulerRunning ? 'يعمل' : 'متوقف' }}
            </p>
            <p class="mt-2 text-sm text-gray-600">
                آخر تشغيل:
                @if ($lastSchedulerRun)
                    <bdi dir="ltr">{{ $lastSchedulerRun->format('Y-m-d H:i') }}</bdi> ({{ $lastSchedulerRun->diffForHumans() }})
                @else
                    لم يعمل أبدًا
                @endif
            </p>
            @unless ($schedulerRunning)
                <p class="mt-2 rounded-lg bg-amber-50 p-2 text-xs text-amber-900">يجب إضافة cron على الخادم يشغّل <code dir="ltr">php artisan schedule:run</code> كل دقيقة.</p>
            @endunless
        </section>

        {{-- Queue --}}
        <section class="card p-4" aria-labelledby="queue-title">
            <h2 id="queue-title" class="mb-3 font-bold">الطوابير</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-gray-600">الاتصال</dt><dd><bdi dir="ltr">{{ $queue['connection'] }}</bdi></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-600">مهام بالانتظار</dt><dd><bdi dir="ltr">{{ $queue['pending'] ?? '—' }}</bdi></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-600">أقدم مهمة منتظرة</dt><dd>{{ $queue['oldest_pending_minutes'] !== null ? $queue['oldest_pending_minutes'].' دقيقة' : '—' }}</dd></div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-600">مهام فاشلة</dt>
                    <dd><a href="{{ route('admin.system.failed-jobs') }}" wire:navigate @class(['font-bold', 'text-red-700' => $queue['failed'] > 0])><bdi dir="ltr">{{ $queue['failed'] ?? '—' }}</bdi></a></dd>
                </div>
            </dl>
        </section>
    </div>

    {{-- All checks --}}
    <section class="card" aria-labelledby="checks-title">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 p-4">
            <h2 id="checks-title" class="font-bold">كل الفحوصات</h2>
            <span class="text-sm text-gray-600">{{ $criticalCount }} حرج · {{ $warningCount }} تحذير</span>
        </div>
        <ul class="divide-y divide-gray-100">
            @foreach ($checks as $check)
                <li wire:key="check-{{ $check->key }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 p-3 text-sm">
                    <span @class(['rounded-full px-2 py-0.5 text-xs font-bold', $styles[$check->status][0]])>{{ $styles[$check->status][1] }}</span>
                    <span class="font-medium">{{ $check->label }}</span>
                    <span class="min-w-0 text-gray-600 sm:ms-auto">{{ $check->message }}</span>
                </li>
            @endforeach
        </ul>
        <p class="border-t border-gray-100 p-3 text-xs text-gray-500">نفس الفحوصات متاحة من سطر الأوامر: <code dir="ltr">php artisan store:check-production</code></p>
    </section>
</div>
