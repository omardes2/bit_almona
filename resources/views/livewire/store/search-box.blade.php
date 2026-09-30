<div x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false" class="relative">
    <form action="{{ route('search') }}" method="GET" role="search" @submit="open = false">
        <label for="header-search" class="sr-only">ابحث عن منتج</label>
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute inset-y-0 start-3 my-auto text-gray-400" />
            <input id="header-search" name="q" type="search" autocomplete="off" enterkeyhint="search"
                   wire:model.live.debounce.350ms="q" @focus="open = true" @input="open = true"
                   placeholder="ابحث عن منتج... مثال: جبنة"
                   class="form-input rounded-full bg-gray-100 ps-10 focus:bg-white" role="combobox"
                   aria-controls="header-search-results" :aria-expanded="open && {{ mb_strlen(trim($q)) >= 2 ? 'true' : 'false' }}">
            <span wire:loading wire:target="q" class="absolute inset-y-0 end-4 my-auto h-4 text-xs text-gray-400" aria-hidden="true">...</span>
        </div>
    </form>

    @if (mb_strlen(trim($q)) >= 2)
        <div x-show="open" x-cloak id="header-search-results"
             class="absolute inset-x-0 top-full z-40 mt-2 overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-gray-200">
            @forelse ($this->results as $product)
                @php($price = $product->price())
                <a href="{{ $product->url() }}" wire:navigate wire:key="suggestion-{{ $product->id }}"
                   class="flex items-center gap-3 border-b border-gray-100 p-2.5 hover:bg-gray-50 focus-visible:bg-gray-50">
                    <x-admin.thumb :url="$product->thumbnailUrl()" alt="" size="size-12" />
                    <span class="min-w-0 flex-1">
                        <span class="line-clamp-1 text-sm font-bold">{{ $product->name }}</span>
                        @unless ($product->isPurchasable())
                            <span class="text-xs text-gray-500">غير متوفر</span>
                        @endunless
                    </span>
                    <x-store.price :price="$price" size="sm" class="flex-col items-end gap-0" />
                </a>
            @empty
                <p class="p-4 text-center text-sm text-gray-500">لا توجد نتائج لـ «{{ $q }}»</p>
            @endforelse

            <a href="{{ route('search', ['q' => $q]) }}" wire:navigate class="block bg-gray-50 p-3 text-center text-sm font-bold text-brand-700 hover:bg-brand-50">
                عرض كل النتائج
            </a>
        </div>
    @endif
</div>
