@props(['name', 'label', 'type' => 'text', 'hint' => null])

<div>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-gray-700">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        {{ $attributes->class([
            'block w-full rounded-lg border bg-white px-3 py-2.5 text-base shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500',
            'border-red-400' => $errors->has($name),
            'border-gray-300' => ! $errors->has($name),
        ]) }}
    >
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
