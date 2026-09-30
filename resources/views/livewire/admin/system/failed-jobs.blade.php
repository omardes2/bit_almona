<div>
    <x-admin.page-header title="المهام الفاشلة" subtitle="مهام الطابور التي فشلت (إشعارات، رسائل...). لا تُعرض بيانات المهمة الداخلية." :back="route('admin.system')">
        @if ($jobs->total() > 0)
            <x-slot:actions>
                <button type="button" wire:click="retryAll" wire:confirm="إعادة كل المهام الفاشلة إلى الطابور؟" class="btn-secondary">إعادة الكل</button>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    <div @class(["divide-y divide-gray-100", "card" => $jobs->total() > 0]) wire:loading.class="opacity-60">
        @forelse ($jobs as $job)
            <div wire:key="job-{{ $job->uuid }}" class="flex flex-wrap items-start gap-3 p-3 text-sm">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-bold" dir="ltr">{{ $job->name }}</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs">طابور: <bdi dir="ltr">{{ $job->queue }}</bdi></span>
                        <span class="text-xs text-gray-500"><bdi dir="ltr">{{ \Illuminate\Support\Carbon::parse($job->failed_at)->format('Y-m-d H:i') }}</bdi></span>
                    </div>
                    <p class="mt-1 break-words text-xs text-red-700" dir="ltr">{{ $job->error }}</p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <button type="button" wire:click="retry('{{ $job->uuid }}')" class="btn-secondary min-h-9 py-1.5">إعادة المحاولة</button>
                    <button type="button" wire:click="forget('{{ $job->uuid }}')" wire:confirm="حذف هذه المهمة نهائيًا؟" class="btn-ghost min-h-9 py-1.5 text-red-600">حذف</button>
                </div>
            </div>
        @empty
            <x-admin.empty-state title="لا توجد مهام فاشلة" description="كل المهام نُفّذت بنجاح." icon="check" />
        @endforelse
    </div>
    <div class="mt-4">{{ $jobs->links('components.admin.pagination') }}</div>

    <p class="mt-4 text-xs text-gray-500">من سطر الأوامر: <code dir="ltr">php artisan queue:failed</code> · <code dir="ltr">php artisan queue:retry all</code> · <code dir="ltr">php artisan queue:forget {uuid}</code></p>
</div>
