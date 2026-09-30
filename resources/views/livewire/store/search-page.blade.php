<div>
    <h1 class="mb-3 text-2xl font-extrabold">البحث</h1>

    <div class="mb-4 flex gap-2">
        <div class="relative flex-1">
            <label for="search-page-q" class="sr-only">ابحث عن منتج</label>
            <x-icon name="search" class="pointer-events-none absolute inset-y-0 start-3 my-auto text-gray-400" />
            <input id="search-page-q" type="search" wire:model.live.debounce.400ms="q" autofocus enterkeyhint="search"
                   placeholder="اكتب اسم المنتج..." class="form-input ps-10 text-lg">
        </div>
        @if ($products)
            <label for="search-sort" class="sr-only">ترتيب النتائج</label>
            <select id="search-sort" wire:model.live="sort" class="form-input w-32 sm:w-44">
                @foreach (\App\Models\Product::STORE_SORTS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        @endif
    </div>

    <div wire:loading.class="opacity-60" wire:target="q,sort,gotoPage,nextPage,previousPage">
        @if ($products === null)
            <p class="mb-4 text-gray-600">اكتب حرفين على الأقل لعرض النتائج.</p>
            @if ($categories->isNotEmpty())
                <h2 class="mb-2 font-bold">أو تصفّح الأقسام</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($categories as $category)
                        <a href="{{ $category->url() }}" wire:navigate class="rounded-full bg-white px-4 py-2 text-sm font-bold shadow-sm ring-1 ring-gray-200 hover:ring-brand-500">{{ $category->name }}</a>
                    @endforeach
                </div>
            @endif
        @elseif ($products->isEmpty())
            <div class="card p-8 text-center">
                <p class="font-bold">لا توجد نتائج لـ «{{ $q }}»</p>
                <p class="mt-1 text-sm text-gray-500">جرّب كلمة أخرى أو تصفّح الأقسام.</p>
            </div>
        @else
            <p class="mb-3 text-sm text-gray-600">{{ $products->total() }} نتيجة</p>
            <x-store.product-grid :products="$products" />
            <div class="mt-6">{{ $products->links('components.admin.pagination') }}</div>
        @endif
    </div>
</div>
