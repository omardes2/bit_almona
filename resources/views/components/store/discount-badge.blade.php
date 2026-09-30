@props(['price'])

@if ($price->discountPercentage() > 0)
    <span {{ $attributes->class('inline-flex items-center gap-1 rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold text-white shadow-sm') }}>
        خصم <bdi dir="ltr">{{ $price->discountPercentage() }}%</bdi>
    </span>
@endif
