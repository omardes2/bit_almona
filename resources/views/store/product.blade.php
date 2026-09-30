@php
    $price = $product->price();
    $buyable = $product->isPurchasable();
    $rules = \App\Services\Cart\QuantityRules::forProduct($product);
    $images = collect([$product->main_image])->filter()->merge($product->images->pluck('path'))->values();
    $imageUrls = $images->map(fn ($path) => \App\Services\Media\ImageStorage::url($path));
    $title = $product->seo_title ?: $product->name;
    $description = $product->seo_description
        ?: \Illuminate\Support\Str::limit(trim(($product->description ? strip_tags($product->description).' ' : '').$product->name.' — '.$product->category->name.' من '.$storeName), 160);
    $canonical = $product->url();
@endphp
<x-layouts::app :title="$title" :description="$description" :canonical="$canonical" :image="$imageUrls->first()" type="product">
    @push('head')
        <meta property="product:price:amount" content="{{ number_format($price->finalPrice, 2, '.', '') }}">
        <meta property="product:price:currency" content="{{ \App\Support\Store::currencyCode() }}">
        <script type="application/ld+json">{!! json_encode(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $product->description ? strip_tags($product->description) : $description,
            'sku' => $product->sku,
            'image' => $imageUrls->all() ?: null,
            'category' => $product->category->name,
            'url' => $canonical,
            'offers' => [
                '@type' => 'Offer',
                'url' => $canonical,
                'priceCurrency' => \App\Support\Store::currencyCode(),
                'price' => number_format($price->finalPrice, 2, '.', ''),
                'availability' => $buyable ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
            ],
        ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endpush

    @include('store.partials.breadcrumbs', ['items' => array_values(array_filter([
        $product->category->parent?->is_active ? [$product->category->parent->name, $product->category->parent->url()] : null,
        [$product->category->name, $product->category->url()],
        [$product->name, null],
    ]))])

    <div class="grid gap-6 md:grid-cols-2">
        {{-- Gallery --}}
        <div x-data="{ active: 0 }">
            <div class="card relative aspect-square overflow-hidden bg-white">
                @forelse ($imageUrls as $i => $url)
                    <img src="{{ $url }}" alt="{{ $product->name }}{{ $i ? ' — صورة '.($i + 1) : '' }}" width="800" height="800"
                         @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif decoding="async"
                         x-show="active === {{ $i }}" @if ($i) x-cloak @endif class="absolute inset-0 size-full object-contain p-4">
                @empty
                    <span class="flex size-full items-center justify-center text-gray-300"><x-icon name="photo" class="size-20" /></span>
                @endforelse
                <x-store.discount-badge :price="$price" class="absolute top-3 start-3 px-3 py-1 text-sm" />
            </div>

            @if ($imageUrls->count() > 1)
                <div class="mt-3 flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="صور المنتج">
                    @foreach ($images as $i => $path)
                        <button type="button" role="tab" @click="active = {{ $i }}" :aria-selected="active === {{ $i }}"
                                :class="active === {{ $i }} ? 'ring-2 ring-brand-600' : 'ring-1 ring-gray-200'"
                                class="shrink-0 overflow-hidden rounded-xl bg-white" aria-label="عرض الصورة {{ $i + 1 }}">
                            <img src="{{ \App\Services\Media\ImageStorage::thumbnailUrl($path) }}" alt="" width="64" height="64" loading="lazy" class="size-16 object-contain">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Details --}}
        <div class="space-y-4">
            <div>
                <h1 class="text-2xl font-extrabold leading-snug md:text-3xl">{{ $product->name }}</h1>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500">
                    <span>الوحدة: {{ $product->unit->label() }}</span>
                    @if ($product->sku)<span>رمز المنتج: <bdi dir="ltr">{{ $product->sku }}</bdi></span>@endif
                </div>
            </div>

            <div class="card space-y-2 p-4">
                <x-store.price :price="$price" size="lg" />
                @if ($price->hasDiscount())
                    <p class="text-sm font-bold text-red-600">
                        وفّر <bdi dir="ltr">{{ \App\Support\Money::short($price->originalPrice - $price->finalPrice) }}</bdi>
                        — خصم <bdi dir="ltr">{{ $price->discountPercentage() }}%</bdi>
                    </p>
                @endif
                <x-store.stock-status :product="$product" class="text-sm" />
            </div>

            @if ($buyable)
                <div x-data="quantityPicker({{ $rules->min }}, {{ $rules->step }}, {{ $rules->maxAllowed() }})" class="space-y-3">
                    <div class="flex items-center gap-3">
                        <span id="qty-label" class="text-sm font-bold">الكمية</span>
                        <div class="flex items-center rounded-xl bg-white ring-1 ring-gray-300" role="group" aria-labelledby="qty-label">
                            <button type="button" @click="increase()" :disabled="!canIncrease" class="flex size-12 items-center justify-center rounded-s-xl hover:bg-gray-100 disabled:opacity-40" aria-label="زيادة الكمية">
                                <x-icon name="plus" />
                            </button>
                            <output class="min-w-16 text-center text-lg font-bold" aria-live="polite"><bdi dir="ltr" x-text="display">{{ \App\Services\Cart\QuantityRules::fromMilli($rules->min) }}</bdi></output>
                            <button type="button" @click="decrease()" :disabled="!canDecrease" class="flex size-12 items-center justify-center rounded-e-xl hover:bg-gray-100 disabled:opacity-40" aria-label="تقليل الكمية">
                                <x-icon name="minus" />
                            </button>
                        </div>
                        <span class="text-sm text-gray-500">{{ $product->unit->label() }}</span>
                    </div>
                    @if ($rules->min > 1000 || $rules->step !== 1000)
                        <p class="text-xs text-gray-500">
                            أقل كمية: <bdi dir="ltr">{{ \App\Services\Cart\QuantityRules::fromMilli($rules->min) }}</bdi>
                            · تزيد بمقدار <bdi dir="ltr">{{ \App\Services\Cart\QuantityRules::fromMilli($rules->step) }}</bdi>
                        </p>
                    @endif

                    <button type="button" x-data="{ busy: false }"
                            @click="busy = true; $dispatch('add-to-cart', { productId: {{ $product->id }}, quantity: display })"
                            @cart-updated.window="busy = false" @cart-add-failed.window="busy = false" :disabled="busy"
                            class="btn-primary w-full py-3.5 text-base">
                        <x-icon name="cart" x-show="!busy" />
                        <span x-show="!busy">أضف للسلة</span>
                        <span x-show="busy" x-cloak>جارٍ الإضافة...</span>
                    </button>
                </div>
            @else
                <button type="button" disabled class="btn w-full bg-gray-100 py-3.5 text-base text-gray-500">غير متوفر حاليًا</button>
            @endif

            @if ($product->description)
                <section class="card p-4">
                    <h2 class="mb-2 font-bold">الوصف</h2>
                    <div class="whitespace-pre-line text-gray-700">{{ $product->description }}</div>
                </section>
            @endif
        </div>
    </div>

    @if ($related->isNotEmpty())
        <x-store.section title="منتجات قد تهمك" class="mt-10">
            <x-store.product-row :products="$related" />
        </x-store.section>
    @endif
</x-layouts::app>
