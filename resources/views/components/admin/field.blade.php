@props(['label', 'for' => null, 'error' => null, 'hint' => null, 'required' => false])

<div {{ $attributes->class('min-w-0') }}>
    <label @if ($for) for="{{ $for }}" @endif class="mb-1.5 block text-sm font-medium text-gray-700">
        {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
    </label>
    {{ $slot }}
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
    @if ($error)
        @error($error)
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    @endif
</div>
