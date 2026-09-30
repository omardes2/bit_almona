{{-- Price from ProductPriceResolver: the current price is big and bold, the old price small and struck through. --}}
@props(['price', 'size' => 'md'])

@php
    $big = ['sm' => 'text-base', 'md' => 'text-lg', 'lg' => 'text-3xl'][$size];
    $small = ['sm' => 'text-xs', 'md' => 'text-sm', 'lg' => 'text-base'][$size];
@endphp

<div {{ $attributes->class('flex flex-wrap items-baseline gap-x-2') }}>
    <span @class([$big, 'font-extrabold', 'text-red-600' => $price->hasDiscount(), 'text-gray-900' => ! $price->hasDiscount()])>
        <span class="sr-only">{{ $price->hasDiscount() ? 'السعر بعد الخصم:' : 'السعر:' }}</span>
        <bdi dir="ltr" class="whitespace-nowrap">{{ \App\Support\Money::short($price->finalPrice) }}</bdi>
    </span>
    @if ($price->hasDiscount())
        <del @class([$small, 'text-gray-500'])>
            <span class="sr-only">بدلًا من</span>
            <bdi dir="ltr" class="whitespace-nowrap">{{ \App\Support\Money::short($price->originalPrice) }}</bdi>
        </del>
    @endif
</div>
