{{-- Horizontal, swipeable row of product cards (home page sections). --}}
@props(['products'])

<div {{ $attributes->class('relative -mx-3 flex snap-x snap-mandatory gap-3 overflow-x-auto px-3 pb-2 md:mx-0 md:grid md:grid-cols-4 md:overflow-visible md:px-0 lg:grid-cols-5') }}>
    @foreach ($products as $product)
        <x-store.product-card :product="$product" class="w-[46%] shrink-0 snap-start sm:w-[31%] md:w-auto" />
    @endforeach
</div>
