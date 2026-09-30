<x-layouts::app :canonical="route('home')">
    <h1 class="sr-only">{{ $storeName }}</h1>

    {{-- Banners --}}
    @if ($banners->isNotEmpty())
        <section aria-label="العروض المميزة" class="-mx-3 md:mx-0"
                 x-data="carousel({{ $banners->count() }})" @mouseenter="pause()" @touchstart.passive="pause()">
            <div x-ref="track" @scroll.debounce.100ms="onScroll()" style="position: relative"
                 class="flex snap-x snap-mandatory overflow-x-auto scroll-smooth [scrollbar-width:none] md:rounded-3xl [&::-webkit-scrollbar]:hidden">
                @foreach ($banners as $banner)
                    @php($safeLink = $banner->link_url && (str_starts_with($banner->link_url, '/') || preg_match('#^https?://#i', $banner->link_url)) ? $banner->link_url : null)
                    <div class="w-full shrink-0 snap-center px-3 md:px-0">
                        @if ($safeLink)
                            <a href="{{ $safeLink }}" @if (str_starts_with($safeLink, '/')) wire:navigate @else rel="noopener" @endif class="block overflow-hidden rounded-2xl md:rounded-3xl">
                        @else
                            <div class="overflow-hidden rounded-2xl md:rounded-3xl">
                        @endif
                            <div class="relative aspect-[16/7] bg-gray-100">
                                <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title ?: $storeName }}" width="1600" height="700"
                                     @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async"
                                     class="size-full object-cover">
                                @if ($banner->title || $banner->description)
                                    <div class="absolute inset-0 flex flex-col justify-end bg-gradient-to-t from-black/60 via-black/10 to-transparent p-4 text-white md:p-8">
                                        @if ($banner->title)<div class="text-lg font-extrabold md:text-3xl">{{ $banner->title }}</div>@endif
                                        @if ($banner->description)<div class="mt-1 line-clamp-2 text-sm md:text-base">{{ $banner->description }}</div>@endif
                                    </div>
                                @endif
                            </div>
                        @if ($safeLink) </a> @else </div> @endif
                    </div>
                @endforeach
            </div>

            @if ($banners->count() > 1)
                <div class="mt-2 flex justify-center gap-1.5">
                    @foreach ($banners as $banner)
                        <button type="button" @click="pause(); go({{ $loop->index }})" class="h-2 rounded-full transition-all"
                                :class="index === {{ $loop->index }} ? 'w-6 bg-brand-600' : 'w-2 bg-gray-300'"
                                aria-label="البنر {{ $loop->iteration }}" :aria-current="index === {{ $loop->index }}"></button>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    {{-- Categories --}}
    @if ($categories->isNotEmpty())
        <x-store.section title="تسوّق حسب القسم" id="categories">
            <div class="relative -mx-3 flex gap-3 overflow-x-auto px-3 pb-2 sm:mx-0 sm:grid sm:grid-cols-4 sm:overflow-visible sm:px-0 md:grid-cols-6 lg:grid-cols-8">
                @foreach ($categories as $category)
                    <a href="{{ $category->url() }}" wire:navigate class="group flex w-24 shrink-0 flex-col items-center gap-2 text-center sm:w-auto">
                        <span class="flex aspect-square w-full items-center justify-center overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 transition group-hover:ring-brand-500 group-focus-visible:ring-2 group-focus-visible:ring-brand-500">
                            @if ($category->image)
                                <img src="{{ $category->thumbnailUrl() }}" alt="" width="200" height="200" loading="lazy" class="size-full object-cover">
                            @else
                                <x-icon name="categories" class="size-10 text-brand-600" />
                            @endif
                        </span>
                        <span class="line-clamp-2 text-sm font-bold leading-tight">{{ $category->name }}</span>
                    </a>
                @endforeach
            </div>
        </x-store.section>
    @endif

    {{-- Offers (section hidden when there are none) --}}
    @if ($offerProducts->isNotEmpty())
        <x-store.section title="عروض اليوم" :link="route('offers')" link-label="كل العروض">
            <x-store.product-row :products="$offerProducts" />
        </x-store.section>
    @endif

    @if ($featured->isNotEmpty())
        <x-store.section title="منتجات مميزة">
            <x-store.product-row :products="$featured" />
        </x-store.section>
    @endif

    @if ($suggested->isNotEmpty())
        <x-store.section title="وصل حديثًا">
            <x-store.product-grid :products="$suggested" />
        </x-store.section>
    @endif

    @if ($banners->isEmpty() && $categories->isEmpty() && $offerProducts->isEmpty() && $featured->isEmpty() && $suggested->isEmpty())
        <div class="card p-10 text-center">
            <h2 class="text-xl font-bold">المتجر قيد التجهيز</h2>
            <p class="mt-2 text-gray-600">نضيف المنتجات حاليًا، ترقبونا قريبًا.</p>
        </div>
    @endif

    @push('head')
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'GroceryStore',
            'name' => $storeName,
            'url' => route('home'),
            'telephone' => \App\Support\Store::phone(),
            'address' => \App\Support\Store::address(),
            'currenciesAccepted' => \App\Support\Store::currencyCode(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endpush
</x-layouts::app>
