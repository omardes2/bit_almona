<nav class="space-y-1">
    @foreach (\App\Support\AdminNavigation::items() as $item)
        @if ($item['route'])
            @php($active = request()->routeIs($item['active']))
            <a href="{{ route($item['route']) }}" wire:navigate
               @class([
                   'flex items-center gap-3 rounded-xl px-3 py-3 text-base font-medium transition',
                   'bg-brand-600 text-white shadow-sm' => $active,
                   'text-gray-700 hover:bg-gray-100' => ! $active,
               ])>
                <x-icon :name="$item['icon']" class="size-6" />
                {{ $item['label'] }}
            </a>
        @else
            <span class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-3 text-base text-gray-400">
                <x-icon :name="$item['icon']" class="size-6" />
                {{ $item['label'] }}
                <span class="ms-auto rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">قريبًا</span>
            </span>
        @endif
    @endforeach
</nav>
