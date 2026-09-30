@props(['title', 'link' => null, 'linkLabel' => 'عرض الكل', 'id' => null])

<section @if ($id) id="{{ $id }}" @endif {{ $attributes->class('mt-8 first:mt-0') }} aria-labelledby="{{ $id ?? \Illuminate\Support\Str::slug($title) ?: 'section' }}-title">
    <div class="mb-3 flex items-end justify-between gap-3">
        <h2 id="{{ $id ?? \Illuminate\Support\Str::slug($title) ?: 'section' }}-title" class="text-xl font-extrabold md:text-2xl">{{ $title }}</h2>
        @if ($link)
            <a href="{{ $link }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-bold text-brand-700 hover:underline">
                {{ $linkLabel }} <x-icon name="chevron-left" class="size-4" />
            </a>
        @endif
    </div>
    {{ $slot }}
</section>
