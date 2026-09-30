{{-- $items: list of [label, url|null] --}}
<nav aria-label="مسار التنقل" class="mb-3 text-sm text-gray-500">
    <ol class="flex flex-wrap items-center gap-1">
        <li><a href="{{ route('home') }}" wire:navigate class="hover:text-brand-700">الرئيسية</a></li>
        @foreach ($items as [$label, $url])
            <li aria-hidden="true"><x-icon name="chevron-left" class="size-3.5" /></li>
            <li>
                @if ($url)
                    <a href="{{ $url }}" wire:navigate class="hover:text-brand-700">{{ $label }}</a>
                @else
                    <span aria-current="page" class="font-bold text-gray-700">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
