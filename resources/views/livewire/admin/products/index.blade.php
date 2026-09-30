<div>
    <x-admin.page-header title="المنتجات" :subtitle="$products->total().' منتج'">
        <x-slot:actions>
            <a href="{{ route('admin.products.create') }}" wire:navigate class="btn-primary">
                <x-admin.icon name="plus" /> إضافة منتج
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @if (! $hasAnyProduct)
        <x-admin.empty-state title="لا توجد منتجات حتى الآن" description="أضف منتجاتك مع الأسعار والصور والمخزون."
            action-label="إضافة أول منتج" :action-url="route('admin.products.create')" />
    @else
        {{-- Filters --}}
        <div x-data="{ open: @js($filtering) }" class="mb-4 space-y-2">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <x-admin.icon name="search" class="pointer-events-none absolute inset-y-0 start-3 my-auto text-gray-400" />
                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="ابحث بالاسم أو SKU..." class="form-input ps-10" aria-label="بحث">
                    <span wire:loading wire:target="search" class="absolute inset-y-0 end-3 my-auto h-5 text-xs text-gray-400">...</span>
                </div>
                <button type="button" @click="open = !open" class="btn-secondary md:hidden" :aria-expanded="open">تصفية</button>
            </div>

            <div :class="open ? 'grid' : 'hidden md:grid'" class="grid-cols-2 gap-2 md:grid-cols-5">
                <select wire:model.live="category" class="form-input col-span-2 md:col-span-1" aria-label="القسم">
                    <option value="">كل الأقسام</option>
                    @foreach ($categories as $row)
                        <option value="{{ $row['category']->id }}">{{ str_repeat('— ', $row['depth']) }}{{ $row['category']->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="status" class="form-input" aria-label="الحالة">
                    <option value="">كل الحالات</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="sort" class="form-input" aria-label="الترتيب">
                    @foreach (\App\Livewire\Admin\Products\ProductIndex::SORTS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <label class="flex min-h-11 items-center gap-2 rounded-xl border border-gray-300 bg-white px-3 text-sm">
                    <input type="checkbox" wire:model.live="lowStock" class="rounded text-brand-600"> مخزون منخفض
                </label>
                <label class="flex min-h-11 items-center gap-2 rounded-xl border border-gray-300 bg-white px-3 text-sm">
                    <input type="checkbox" wire:model.live="trashed" class="rounded text-brand-600"> المحذوفات
                </label>
            </div>
        </div>

        <div wire:loading.class="opacity-60" wire:target="search,category,status,sort,lowStock,trashed,gotoPage,nextPage,previousPage">
            @if ($products->isEmpty())
                <x-admin.empty-state icon="search" title="لا توجد منتجات مطابقة" description="جرّب تغيير كلمات البحث أو الفلاتر.">
                    <button type="button" wire:click="clearFilters" class="btn-secondary mt-4">مسح الفلاتر</button>
                </x-admin.empty-state>
            @else
                {{-- Mobile: cards --}}
                <div class="space-y-2 md:hidden">
                    @foreach ($products as $product)
                        <div wire:key="m-product-{{ $product->id }}" class="card flex gap-3 p-3">
                            <x-admin.thumb :url="$product->thumbnailUrl()" :alt="$product->name" size="size-20" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ $product->trashed() ? '#' : route('admin.products.edit', $product) }}" wire:navigate class="line-clamp-2 font-bold leading-snug">{{ $product->name }}</a>
                                    @include('livewire.admin.products.partials.actions', ['product' => $product])
                                </div>
                                <div class="mt-0.5 text-xs text-gray-500">{{ $product->category?->name }} @if ($product->sku) · <span dir="ltr">{{ $product->sku }}</span> @endif</div>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                                    @include('livewire.admin.products.partials.price', ['product' => $product])
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    @include('livewire.admin.products.partials.badges', ['product' => $product])
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Desktop: table --}}
                <div class="card hidden overflow-x-auto md:block">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-start font-medium">المنتج</th>
                                <th class="px-3 py-3 text-start font-medium">القسم</th>
                                <th class="px-3 py-3 text-start font-medium">السعر</th>
                                <th class="px-3 py-3 text-start font-medium">المخزون</th>
                                <th class="px-3 py-3 text-start font-medium">الحالة</th>
                                <th class="px-3 py-3 text-start font-medium">آخر تعديل</th>
                                <th class="px-3 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($products as $product)
                                <tr wire:key="d-product-{{ $product->id }}" class="hover:bg-gray-50/60">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <x-admin.thumb :url="$product->thumbnailUrl()" :alt="$product->name" size="size-12" />
                                            <div class="min-w-0">
                                                <a href="{{ $product->trashed() ? '#' : route('admin.products.edit', $product) }}" wire:navigate class="line-clamp-1 font-bold hover:text-brand-700">{{ $product->name }}</a>
                                                <div class="text-xs text-gray-500" dir="ltr">{{ $product->sku ?? '—' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600">{{ $product->category?->name }}</td>
                                    <td class="px-3 py-3"><div class="flex flex-col">@include('livewire.admin.products.partials.price', ['product' => $product])</div></td>
                                    <td class="px-3 py-3">
                                        <span @class(['font-bold', 'text-red-600' => $product->isLowStock()])>{{ \App\Support\Decimal::trim($product->stock_quantity) }}</span>
                                        <span class="text-xs text-gray-500">{{ $product->unit->label() }}</span>
                                    </td>
                                    <td class="px-3 py-3"><div class="flex flex-wrap gap-1">@include('livewire.admin.products.partials.badges', ['product' => $product, 'hideStock' => true])</div></td>
                                    <td class="px-3 py-3 text-xs text-gray-500" title="{{ $product->updated_at }}">{{ $product->updated_at?->diffForHumans() }}</td>
                                    <td class="px-3 py-3">@include('livewire.admin.products.partials.actions', ['product' => $product])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $products->links('components.admin.pagination') }}</div>
            @endif
        </div>
    @endif
</div>
