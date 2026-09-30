<div class="mx-auto max-w-2xl space-y-4">
    @include('account.partials.nav', ['active' => 'notifications'])

    <div>
        <h1 class="text-2xl font-bold">إعدادات التنبيهات</h1>
        <p class="text-sm text-gray-600">اختر كيف تصلك تحديثات طلباتك.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-brand-50 p-3 text-sm text-brand-700" role="status">{{ session('status') }}</div>
    @endif

    <form wire:submit="save" class="card divide-y divide-gray-100">
        @foreach (\App\Livewire\Account\NotificationPreferences::CHANNELS as $channel => [$label, $description])
            <label wire:key="pref-{{ $channel }}" @class(['flex items-center gap-3 p-4', 'cursor-pointer' => $available[$channel], 'opacity-70' => ! $available[$channel]])>
                <span class="min-w-0 flex-1">
                    <span class="block font-bold">{{ $label }}</span>
                    <span class="block text-xs text-gray-500">{{ $description }}</span>
                </span>
                @if ($available[$channel])
                    <input type="checkbox" wire:model="preferences.{{ $channel }}" class="size-5 rounded border-gray-300 text-brand-600">
                @else
                    <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-bold text-gray-600">غير متاح حاليًا</span>
                @endif
            </label>
        @endforeach
        <div class="p-4">
            <button type="submit" class="btn-primary px-6">حفظ</button>
        </div>
    </form>

    <p class="text-xs text-gray-500">ستبقى إشعارات الطلب ظاهرة دائمًا في صفحة «طلباتي».</p>
</div>
