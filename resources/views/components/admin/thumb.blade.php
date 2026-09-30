@props(['url' => null, 'alt' => '', 'size' => 'size-14'])

@if ($url)
    <img src="{{ $url }}" alt="{{ $alt }}" loading="lazy" decoding="async"
         {{ $attributes->class([$size, 'shrink-0 rounded-xl bg-gray-100 object-cover']) }}>
@else
    <div {{ $attributes->class([$size, 'flex shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-300']) }}>
        <x-icon name="photo" class="size-6" />
    </div>
@endif
