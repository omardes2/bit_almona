<div>
    <x-admin.page-header title="البنرات" subtitle="الإعلانات التي تظهر في واجهة المتجر">
        <x-slot:actions>
            <a href="{{ route('admin.banners.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" /> إضافة بنر
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @if (! $hasAnyBanner)
        <x-admin.empty-state icon="banners" title="لا توجد بنرات حتى الآن" description="أضف صورًا إعلانية مع رابط وتاريخ عرض."
            action-label="إضافة أول بنر" :action-url="route('admin.banners.create')" />
    @else
        <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
            <button type="button" wire:click="$set('status', '')" @class(['btn shrink-0 min-h-9 py-1.5', 'bg-gray-900 text-white' => $status === '', 'bg-white text-gray-700 ring-1 ring-gray-300' => $status !== ''])>الكل</button>
            @foreach ($statuses as $s)
                <button type="button" wire:click="$set('status', '{{ $s->value }}')" @class(['btn shrink-0 min-h-9 py-1.5', 'bg-gray-900 text-white' => $status === $s->value, 'bg-white text-gray-700 ring-1 ring-gray-300' => $status !== $s->value])>{{ $s->label() }}</button>
            @endforeach
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-60" wire:target="status,gotoPage,nextPage,previousPage">
            @forelse ($banners as $banner)
                @php($state = $banner->scheduleStatus())
                <div wire:key="banner-{{ $banner->id }}" class="card overflow-hidden">
                    <div class="relative">
                        <img src="{{ $banner->thumbnailUrl() }}" alt="{{ $banner->title }}" loading="lazy" class="aspect-[16/7] w-full bg-gray-100 object-cover">
                        <x-admin.status-badge :status="$state" class="absolute top-2 start-2 shadow" />
                        <span class="absolute top-2 end-2 rounded-full bg-black/60 px-2 py-0.5 text-xs text-white">#{{ $banner->sort_order }}</span>
                    </div>
                    <div class="space-y-1 p-3">
                        <div class="font-bold">{{ $banner->title ?: 'بدون عنوان' }}</div>
                        @if ($banner->link_url)
                            <div class="truncate text-xs text-gray-500" dir="ltr">{{ $banner->link_url }}</div>
                        @endif
                        <div class="text-xs text-gray-500">
                            من <bdi dir="ltr">{{ $banner->starts_at?->format('Y-m-d H:i') ?? 'الآن' }}</bdi> إلى <bdi dir="ltr">{{ $banner->ends_at?->format('Y-m-d H:i') ?? 'بدون نهاية' }}</bdi>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 border-t border-gray-100 p-2">
                        <a href="{{ route('admin.banners.edit', $banner) }}" wire:navigate class="btn-ghost flex-1"><x-icon name="edit" class="size-4" /> تعديل</a>
                        <button type="button" wire:click="toggleActive({{ $banner->id }})" class="btn-ghost flex-1">{{ $banner->is_active ? 'تعطيل' : 'تفعيل' }}</button>
                        <button type="button" wire:click="move({{ $banner->id }}, -1)" class="btn-ghost px-2" aria-label="تحريك للأعلى"><x-icon name="up" class="size-4" /></button>
                        <button type="button" wire:click="move({{ $banner->id }}, 1)" class="btn-ghost px-2" aria-label="تحريك للأسفل"><x-icon name="down" class="size-4" /></button>
                        <button type="button" wire:click="delete({{ $banner->id }})" wire:confirm="حذف هذا البنر نهائيًا؟" class="btn-ghost px-2 text-red-600" aria-label="حذف"><x-icon name="trash" class="size-4" /></button>
                    </div>
                </div>
            @empty
                <div class="sm:col-span-2 xl:col-span-3"><x-admin.empty-state icon="banners" title="لا توجد بنرات بهذه الحالة" /></div>
            @endforelse
        </div>

        <div class="mt-4">{{ $banners->links('components.admin.pagination') }}</div>
    @endif
</div>
