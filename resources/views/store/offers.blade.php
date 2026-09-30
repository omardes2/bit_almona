<x-layouts::app :title="'عروض '.$storeName" :description="'أحدث عروض وخصومات '.$storeName.' — أسعار مخفضة لفترة محدودة.'"
                :canonical="route('offers').($offers->currentPage() > 1 ? '?page='.$offers->currentPage() : '')">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold md:text-3xl">عروض {{ $storeName }}</h1>
            <p class="text-sm text-gray-600">عروض سارية الآن لفترة محدودة</p>
        </div>
        @if ($offers->isNotEmpty())
            <form method="GET" x-data>
                <label for="offers-sort" class="sr-only">ترتيب العروض</label>
                <select id="offers-sort" name="sort" @change="$el.form.submit()" class="form-input w-44">
                    @foreach (\App\Http\Controllers\Store\OffersController::SORTS as $key => $label)
                        <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if ($offers->isEmpty())
        <div class="card p-8 text-center">
            <x-icon name="offers" class="mx-auto mb-3 size-12 text-gray-300" />
            <p class="font-bold">لا توجد عروض حاليًا</p>
            <p class="mt-1 text-sm text-gray-500">تابعنا، نضيف عروضًا جديدة باستمرار.</p>
            <a href="{{ route('home') }}" wire:navigate class="btn-secondary mt-4">تصفّح المنتجات</a>
        </div>
    @else
        <x-store.product-grid :products="$offers->pluck('product')" />
        <div class="mt-6">{{ $offers->links('store.partials.pagination') }}</div>
    @endif
</x-layouts::app>
