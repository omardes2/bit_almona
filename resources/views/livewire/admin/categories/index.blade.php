<div>
    <x-admin.page-header title="الأقسام" subtitle="الأقسام الرئيسية والفرعية">
        <x-slot:actions>
            <a href="{{ route('admin.categories.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" /> إضافة قسم
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @if ($total === 0)
        <x-admin.empty-state icon="categories" title="لا توجد أقسام حتى الآن"
            description="ابدأ بإضافة الأقسام الرئيسية مثل «ألبان وأجبان» ثم أضف الأقسام الفرعية."
            action-label="إضافة أول قسم" :action-url="route('admin.categories.create')" />
    @else
        <div class="mb-4 flex flex-col gap-2 sm:flex-row">
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute inset-y-0 start-3 my-auto text-gray-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="ابحث باسم القسم..." class="form-input ps-10">
            </div>
            <select wire:model.live="sort" class="form-input sm:w-48" aria-label="الترتيب">
                <option value="sort_order">حسب الترتيب</option>
                <option value="name">حسب الاسم</option>
            </select>
        </div>

        <div class="card divide-y divide-gray-100" wire:loading.class="opacity-60">
            @forelse ($rows as $row)
                @php($category = $row['category'])
                <div wire:key="category-{{ $category->id }}" class="flex items-center gap-3 p-3 sm:p-4"
                     style="padding-inline-start: {{ 0.75 + $row['depth'] * 1.5 }}rem">
                    @if ($row['depth'] > 0)
                        <span class="text-gray-300" aria-hidden="true">↳</span>
                    @endif

                    <x-admin.thumb :url="$category->thumbnailUrl()" :alt="$category->name" size="size-12" />

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate font-bold">{{ $category->name }}</span>
                            @if ($row['depth'] === 0)
                                <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs text-brand-700">رئيسي</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">فرعي</span>
                            @endif
                            @unless ($category->is_active)
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">معطل</span>
                            @endunless
                        </div>
                        <div class="mt-0.5 text-xs text-gray-500">
                            {{ $category->products_count }} منتج
                            @if ($category->children_count) · {{ $category->children_count }} قسم فرعي @endif
                            · ترتيب {{ $category->sort_order }}
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        @if ($sort === 'sort_order' && $search === '')
                            <div class="hidden flex-col sm:flex">
                                <button type="button" wire:click="move({{ $category->id }}, -1)" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="تحريك للأعلى"><x-icon name="up" class="size-4" /></button>
                                <button type="button" wire:click="move({{ $category->id }}, 1)" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="تحريك للأسفل"><x-icon name="down" class="size-4" /></button>
                            </div>
                        @endif

                        <div x-data="{ open: false }" class="relative">
                            <button type="button" @click="open = !open" @click.outside="open = false" class="btn-ghost" aria-label="خيارات">⋮</button>
                            <div x-show="open" x-cloak x-transition.origin.top.left
                                 class="absolute end-0 z-20 mt-1 w-52 overflow-hidden rounded-xl bg-white py-1 shadow-lg ring-1 ring-gray-200">
                                <a href="{{ route('admin.categories.edit', $category) }}" wire:navigate class="flex items-center gap-2 px-4 py-3 text-sm hover:bg-gray-50"><x-icon name="edit" class="size-4" /> تعديل</a>
                                <a href="{{ route('admin.categories.create', ['parent' => $category->id]) }}" wire:navigate class="flex items-center gap-2 px-4 py-3 text-sm hover:bg-gray-50"><x-icon name="plus" class="size-4" /> إضافة قسم فرعي</a>
                                <button type="button" wire:click="toggleActive({{ $category->id }})" @click="open = false" class="flex w-full items-center gap-2 px-4 py-3 text-sm hover:bg-gray-50">
                                    {{ $category->is_active ? 'تعطيل' : 'تفعيل' }}
                                </button>
                                @if ($sort === 'sort_order' && $search === '')
                                    <button type="button" wire:click="move({{ $category->id }}, -1)" class="flex w-full items-center gap-2 px-4 py-3 text-sm hover:bg-gray-50 sm:hidden"><x-icon name="up" class="size-4" /> تحريك للأعلى</button>
                                    <button type="button" wire:click="move({{ $category->id }}, 1)" class="flex w-full items-center gap-2 px-4 py-3 text-sm hover:bg-gray-50 sm:hidden"><x-icon name="down" class="size-4" /> تحريك للأسفل</button>
                                @endif
                                <button type="button" wire:click="delete({{ $category->id }})" @click="open = false"
                                        wire:confirm="هل أنت متأكد من حذف القسم «{{ $category->name }}»؟ لا يمكن التراجع."
                                        class="flex w-full items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50">
                                    <x-icon name="trash" class="size-4" /> حذف
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <p class="p-8 text-center text-sm text-gray-500">لا توجد أقسام مطابقة للبحث.</p>
            @endforelse
        </div>
    @endif
</div>
