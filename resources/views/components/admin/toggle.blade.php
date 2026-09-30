@props(['label', 'description' => null])

<label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-3">
    <span>
        <span class="block text-sm font-medium text-gray-800">{{ $label }}</span>
        @if ($description)
            <span class="block text-xs text-gray-500">{{ $description }}</span>
        @endif
    </span>
    <span class="relative inline-flex shrink-0">
        <input type="checkbox" {{ $attributes }} class="peer sr-only">
        <span class="h-7 w-12 rounded-full bg-gray-300 transition peer-checked:bg-brand-600 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40"></span>
        <span class="absolute top-1 start-1 size-5 rounded-full bg-white shadow transition peer-checked:-translate-x-5"></span>
    </span>
</label>
