@props(['status', 'label' => null])

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold whitespace-nowrap', $status->badgeClasses()]) }}>
    {{ $label ?? $status->label() }}
</span>
