@props(['title', 'subtitle' => null, 'back' => null])

<div class="mb-4 flex flex-wrap items-center justify-between gap-3 sm:mb-6">
    <div class="flex min-w-0 items-center gap-2">
        @if ($back)
            <a href="{{ $back }}" wire:navigate class="rounded-lg p-2 text-gray-500 hover:bg-gray-200" aria-label="رجوع">
                <x-icon name="arrow-right" class="size-6" />
            </a>
        @endif
        <div class="min-w-0">
            <h1 class="truncate text-xl font-bold sm:text-2xl">{{ $title }}</h1>
            @if ($subtitle)
                <p class="text-sm text-gray-500">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
