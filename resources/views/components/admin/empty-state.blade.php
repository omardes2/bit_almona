@props(['title', 'description' => null, 'actionLabel' => null, 'actionUrl' => null, 'icon' => 'products'])

<div class="card flex flex-col items-center px-6 py-12 text-center">
    <div class="mb-4 rounded-full bg-brand-50 p-4 text-brand-600">
        <x-admin.icon :name="$icon" class="size-8" />
    </div>
    <h2 class="text-lg font-bold">{{ $title }}</h2>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-gray-500">{{ $description }}</p>
    @endif
    @if ($actionLabel && $actionUrl)
        <a href="{{ $actionUrl }}" wire:navigate class="btn-primary mt-5">
            <x-admin.icon name="plus" /> {{ $actionLabel }}
        </a>
    @endif
    {{ $slot }}
</div>
