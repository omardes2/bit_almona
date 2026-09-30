@props(['products'])

<div {{ $attributes->class('grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5') }}>
    @foreach ($products as $product)
        <x-store.product-card :product="$product" wire:key="product-{{ $product->id }}" />
    @endforeach
</div>
