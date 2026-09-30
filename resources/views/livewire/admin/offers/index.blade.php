<div>
    <x-admin.page-header title="العروض" :subtitle="$offers->total().' عرض'">
        <x-slot:actions>
            <a href="{{ route('admin.offers.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" /> إنشاء عرض
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @if (! $hasAnyOffer)
        <x-admin.empty-state icon="offers" title="لا توجد عروض حتى الآن" description="أنشئ عرضًا بسعر مخفض لمدة محددة على أي منتج."
            action-label="إنشاء أول عرض" :action-url="route('admin.offers.create')" />
    @else
        <div class="mb-4 space-y-2">
            <div class="flex gap-2">
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute inset-y-0 start-3 my-auto text-gray-400" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="ابحث باسم المنتج أو SKU..." class="form-input ps-10">
            </div>
                <select wire:model.live="sort" class="form-input w-36 shrink-0 text-sm sm:w-56" aria-label="الترتيب">
                    @foreach (\App\Livewire\Admin\Offers\OfferIndex::SORTS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button type="button" wire:click="$set('status', '')" @class(['btn shrink-0 min-h-9 py-1.5', 'bg-gray-900 text-white' => $status === '', 'bg-white text-gray-700 ring-1 ring-gray-300' => $status !== ''])>الكل</button>
                @foreach ($statuses as $s)
                    <button type="button" wire:click="$set('status', '{{ $s->value }}')" @class(['btn shrink-0 min-h-9 py-1.5', 'bg-gray-900 text-white' => $status === $s->value, 'bg-white text-gray-700 ring-1 ring-gray-300' => $status !== $s->value])>{{ $s->offerLabel() }}</button>
                @endforeach
            </div>
        </div>

        <div wire:loading.class="opacity-60" wire:target="status,search,sort,gotoPage,nextPage,previousPage">
            @forelse ($offers as $offer)
                @php($state = $offer->scheduleStatus())
                <div wire:key="offer-{{ $offer->id }}" class="card mb-2 flex gap-3 p-3 sm:items-center sm:p-4">
                    <x-admin.thumb :url="$offer->thumbnailUrl()" :alt="$offer->product?->name" size="size-16 sm:size-20" />

                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <a href="{{ route('admin.offers.edit', $offer) }}" wire:navigate class="line-clamp-2 font-bold leading-snug">{{ $offer->product?->name ?? 'منتج غير موجود' }}</a>
                                @if ($offer->title)<div class="text-xs text-gray-500">{{ $offer->title }}</div>@endif
                                @if ($offer->product?->trashed())<span class="text-xs font-bold text-red-600">المنتج محذوف</span>@endif
                            </div>
                            <x-admin.status-badge :status="$state" :label="$state->offerLabel()" />
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                            <span class="font-bold text-brand-700">{{ \App\Support\Money::format($offer->offer_price) }}</span>
                            <span class="text-gray-400 line-through">{{ \App\Support\Money::format($offer->original_price) }}</span>
                            <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-bold text-red-700">خصم {{ $offer->discountPercentage() }}%</span>
                            <span class="text-xs text-gray-500">ترتيب {{ $offer->sort_order }}</span>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            من <bdi dir="ltr">{{ $offer->starts_at?->format('Y-m-d H:i') ?? 'الآن' }}</bdi>
                            إلى <bdi dir="ltr">{{ $offer->ends_at?->format('Y-m-d H:i') ?? 'بدون نهاية' }}</bdi>
                        </div>
                    </div>

                    <div x-data="{ open: false }" class="relative shrink-0 self-start">
                        <button type="button" @click="open = !open" @click.outside="open = false" class="btn-ghost min-h-9 px-2" aria-label="خيارات">⋮</button>
                        <div x-show="open" x-cloak x-transition.origin.top.left class="absolute end-0 z-20 mt-1 w-48 overflow-hidden rounded-xl bg-white py-1 text-sm shadow-lg ring-1 ring-gray-200">
                            <a href="{{ route('admin.offers.edit', $offer) }}" wire:navigate class="flex items-center gap-2 px-4 py-3 hover:bg-gray-50"><x-icon name="edit" class="size-4" /> تعديل</a>
                            <button type="button" wire:click="toggleActive({{ $offer->id }})" @click="open = false" class="flex w-full items-center gap-2 px-4 py-3 hover:bg-gray-50">{{ $offer->is_active ? 'إيقاف العرض' : 'تفعيل العرض' }}</button>
                            @if ($sort === 'sort_order')
                                <button type="button" wire:click="move({{ $offer->id }}, -1)" class="flex w-full items-center gap-2 px-4 py-3 hover:bg-gray-50"><x-icon name="up" class="size-4" /> تحريك للأعلى</button>
                                <button type="button" wire:click="move({{ $offer->id }}, 1)" class="flex w-full items-center gap-2 px-4 py-3 hover:bg-gray-50"><x-icon name="down" class="size-4" /> تحريك للأسفل</button>
                            @endif
                            <button type="button" wire:click="delete({{ $offer->id }})" @click="open = false" wire:confirm="حذف هذا العرض نهائيًا؟ (يمكنك إيقافه بدل حذفه)" class="flex w-full items-center gap-2 px-4 py-3 text-red-600 hover:bg-red-50"><x-icon name="trash" class="size-4" /> حذف</button>
                        </div>
                    </div>
                </div>
            @empty
                <x-admin.empty-state icon="search" title="لا توجد عروض مطابقة" />
            @endforelse

            <div class="mt-4">{{ $offers->links('components.admin.pagination') }}</div>
        </div>
    @endif
</div>
