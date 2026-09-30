<div>
    <x-admin.page-header title="مناطق التوصيل" subtitle="رسوم التوصيل والحد الأدنى لكل منطقة">
        <x-slot:actions>
            <a href="{{ route('admin.delivery-zones.create') }}" wire:navigate class="btn-primary"><x-icon name="plus" /> إضافة منطقة</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if ($zones->isEmpty())
        <x-admin.empty-state icon="zones" title="لا توجد مناطق توصيل حتى الآن" description="أضف المناطق التي توصّل إليها مع رسوم كل منطقة. بدونها لا يستطيع الزبائن إتمام الطلب."
            action-label="إضافة أول منطقة" :action-url="route('admin.delivery-zones.create')" />
    @else
        <div class="card divide-y divide-gray-100">
            @foreach ($zones as $zone)
                <div wire:key="zone-{{ $zone->id }}" class="flex items-center gap-3 p-3 sm:p-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold">{{ $zone->name }}</span>
                            <span @class(['rounded-full px-2 py-0.5 text-xs font-bold', 'bg-green-100 text-green-800' => $zone->is_active, 'bg-red-100 text-red-700' => ! $zone->is_active])>
                                {{ $zone->is_active ? 'فعالة' : 'معطلة' }}
                            </span>
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-600">
                            <span>التوصيل: <bdi dir="ltr" class="font-bold text-gray-900">{{ \App\Support\Money::format($zone->delivery_fee) }}</bdi></span>
                            <span>الحد الأدنى: {!! $zone->min_order_amount !== null ? '<bdi dir="ltr">'.e(\App\Support\Money::format($zone->min_order_amount)).'</bdi>' : 'بدون' !!}</span>
                            <span class="text-xs text-gray-400">{{ $zone->orders_count }} طلب</span>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <button type="button" wire:click="move({{ $zone->id }}, -1)" class="btn-ghost px-2" aria-label="تحريك للأعلى"><x-icon name="up" class="size-4" /></button>
                        <button type="button" wire:click="move({{ $zone->id }}, 1)" class="btn-ghost px-2" aria-label="تحريك للأسفل"><x-icon name="down" class="size-4" /></button>
                        <div x-data="{ open: false }" class="relative">
                            <button type="button" @click="open = !open" @click.outside="open = false" class="btn-ghost" aria-label="خيارات">⋮</button>
                            <div x-show="open" x-cloak class="absolute end-0 z-20 mt-1 w-48 overflow-hidden rounded-xl bg-white py-1 text-sm shadow-lg ring-1 ring-gray-200">
                                <a href="{{ route('admin.delivery-zones.edit', $zone) }}" wire:navigate class="flex items-center gap-2 px-4 py-3 hover:bg-gray-50"><x-icon name="edit" class="size-4" /> تعديل</a>
                                <button type="button" wire:click="toggleActive({{ $zone->id }})" @click="open = false" class="flex w-full px-4 py-3 hover:bg-gray-50">{{ $zone->is_active ? 'تعطيل' : 'تفعيل' }}</button>
                                <button type="button" wire:click="delete({{ $zone->id }})" @click="open = false" wire:confirm="حذف منطقة «{{ $zone->name }}»؟"
                                        class="flex w-full items-center gap-2 px-4 py-3 text-red-600 hover:bg-red-50"><x-icon name="trash" class="size-4" /> حذف</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
