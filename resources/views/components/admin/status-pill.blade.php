@props(['active'])

<span {{ $attributes->class(['inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-bold', 'bg-green-100 text-green-800' => $active, 'bg-red-100 text-red-700' => ! $active]) }}>
    {{ $active ? 'فعّال' : 'موقوف' }}
</span>
