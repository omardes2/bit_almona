@props(['data'])
@php
    [$icon, $classes] = match ($data['kind'] ?? null) {
        'order_placed' => ['orders', 'bg-blue-100 text-blue-700'],
        'order_cancelled' => ['close', 'bg-red-100 text-red-700'],
        'low_stock' => ['alert', 'bg-amber-100 text-amber-700'],
        'out_of_stock' => ['alert', 'bg-red-100 text-red-700'],
        default => ['alert', 'bg-gray-100 text-gray-600'],
    };
@endphp
<span {{ $attributes->class(['flex size-9 shrink-0 items-center justify-center rounded-full', $classes]) }}><x-icon :name="$icon" class="size-5" /></span>
