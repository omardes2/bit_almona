{{-- Availability as text (never colour only). --}}
@props(['product'])

@php
    $status = match (true) {
        $product->status !== \App\Enums\ProductStatus::Available => ['غير متوفر', 'text-gray-500'],
        ! $product->isPurchasable() => ['نفد من المخزون', 'text-red-600'],
        $product->isLowStock() => ['كمية محدودة', 'text-amber-700'],
        default => ['متوفر', 'text-green-700'],
    };
@endphp

<span {{ $attributes->class(['text-xs font-bold', $status[1]]) }}>{{ $status[0] }}</span>
