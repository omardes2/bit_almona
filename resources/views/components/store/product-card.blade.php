@props(['product'])

@php
    $price = $product->price();
    $buyable = $product->isPurchasable();
    $url = $product->url();
@endphp

<article {{ $attributes->class('card group relative flex h-full flex-col overflow-hidden') }}>
    <a href="{{ $url }}" wire:navigate class="relative block aspect-square bg-white" tabindex="-1" aria-hidden="true">
        @if ($product->main_image)
            <img src="{{ $product->thumbnailUrl() }}" alt="{{ $product->name }}" width="400" height="400" loading="lazy" decoding="async"
                 @class(['size-full object-contain p-2 transition group-hover:scale-[1.03]', 'opacity-50 grayscale' => ! $buyable])>
        @else
            <span class="flex size-full items-center justify-center text-gray-300"><x-icon name="photo" class="size-12" /></span>
        @endif

        <x-store.discount-badge :price="$price" class="absolute top-2 start-2" />

        @unless ($buyable)
            <span class="absolute inset-x-2 bottom-2 rounded-lg bg-gray-900/80 py-1 text-center text-xs font-bold text-white">
                {{ $product->status === \App\Enums\ProductStatus::Available ? 'نفد من المخزون' : 'غير متوفر' }}
            </span>
        @endunless
    </a>

    <div class="flex flex-1 flex-col gap-1.5 p-3">
        <h3 class="line-clamp-2 min-h-10 text-sm font-bold leading-5">
            <a href="{{ $url }}" wire:navigate class="hover:text-brand-700 focus-visible:underline">{{ $product->name }}</a>
        </h3>

        <div class="text-xs text-gray-500">{{ $product->unit->label() }}</div>

        <x-store.price :price="$price" size="md" class="mt-auto" />

        @if ($buyable)
            <button type="button"
                    x-data="{ busy: false }"
                    @click="busy = true; $dispatch('add-to-cart', { productId: {{ $product->id }} })"
                    @cart-updated.window="busy = false" @cart-add-failed.window="busy = false"
                    :disabled="busy"
                    class="btn-primary mt-1 w-full"
                    aria-label="أضف {{ $product->name }} للسلة">
                <x-icon name="cart" class="size-5" x-show="!busy" />
                <span x-show="!busy">أضف للسلة</span>
                <span x-show="busy" x-cloak>جارٍ الإضافة...</span>
            </button>
        @else
            <button type="button" disabled class="btn mt-1 w-full bg-gray-100 text-gray-500">غير متوفر</button>
        @endif
    </div>
</article>
