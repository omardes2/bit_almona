@php
    $canonical = $category->url().($products->currentPage() > 1 ? '?page='.$products->currentPage() : '');
    $description = $category->description ?: 'تسوّق '.$category->name.' من '.$storeName.'. '.$products->total().' منتج بأسعار واضحة وعروض مستمرة.';
@endphp
<x-layouts::app :title="$category->name" :description="$description" :canonical="$canonical" :image="$category->image ? \App\Services\Media\ImageStorage::url($category->image) : null">
    @include('store.partials.breadcrumbs', ['items' => array_filter([
        $category->parent ? [$category->parent->name, $category->parent->url()] : null,
        [$category->name, null],
    ])])

    <div class="mb-4 flex items-center gap-4">
        @if ($category->image)
            <img src="{{ $category->thumbnailUrl() }}" alt="" width="80" height="80" class="size-16 rounded-2xl object-cover md:size-20">
        @endif
        <div>
            <h1 class="text-2xl font-extrabold md:text-3xl">{{ $category->name }}</h1>
            @if ($category->description)
                <p class="mt-1 text-sm text-gray-600">{{ $category->description }}</p>
            @endif
        </div>
    </div>

    @if ($category->children->isNotEmpty())
        <nav aria-label="الأقسام الفرعية" class="relative -mx-3 mb-4 flex gap-2 overflow-x-auto px-3 pb-1 md:mx-0 md:flex-wrap md:px-0">
            @foreach ($category->children as $child)
                <a href="{{ $child->url() }}" wire:navigate class="flex shrink-0 items-center gap-2 rounded-full bg-white py-1.5 pe-4 ps-1.5 text-sm font-bold shadow-sm ring-1 ring-gray-200 hover:ring-brand-500">
                    <x-admin.thumb :url="$child->thumbnailUrl()" alt="" size="size-8" class="rounded-full" />
                    {{ $child->name }}
                </a>
            @endforeach
        </nav>
    @endif

    <form method="GET" x-data class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">{{ $products->total() }} منتج</p>
        <div class="flex items-center gap-2">
            <label class="flex min-h-11 items-center gap-2 rounded-xl bg-white px-3 text-sm ring-1 ring-gray-300">
                <input type="checkbox" name="available" value="1" @checked($onlyAvailable) @change="$el.form.submit()" class="rounded text-brand-600">
                المتوفر فقط
            </label>
            <label for="sort" class="sr-only">ترتيب المنتجات</label>
            <select id="sort" name="sort" @change="$el.form.submit()" class="form-input w-36 sm:w-44">
                @foreach (\App\Models\Product::STORE_SORTS as $key => $label)
                    <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="btn-secondary">تطبيق</button></noscript>
        </div>
    </form>

    @if ($products->isEmpty())
        <div class="card p-10 text-center">
            <x-icon name="products" class="mx-auto mb-3 size-12 text-gray-300" />
            <p class="font-bold">لا توجد منتجات في هذا القسم حاليًا</p>
            <a href="{{ route('home') }}" wire:navigate class="btn-secondary mt-4">العودة للرئيسية</a>
        </div>
    @else
        <x-store.product-grid :products="$products" />
        <div class="mt-6">{{ $products->onEachSide(1)->links('store.partials.pagination') }}</div>
    @endif
</x-layouts::app>
