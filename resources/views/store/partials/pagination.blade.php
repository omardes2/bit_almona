{{-- Plain-link pagination (crawlable) for controller pages. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="التنقل بين الصفحات" class="flex items-center justify-between gap-2">
        @if ($paginator->onFirstPage())
            <span class="btn border border-gray-200 bg-white text-gray-300" aria-disabled="true"><x-icon name="arrow-right" class="size-4" /> السابق</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-secondary"><x-icon name="arrow-right" class="size-4" /> السابق</a>
        @endif

        <span class="text-sm text-gray-600">صفحة {{ $paginator->currentPage() }} من {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-secondary">التالي <x-icon name="arrow-right" class="size-4 rotate-180" /></a>
        @else
            <span class="btn border border-gray-200 bg-white text-gray-300" aria-disabled="true">التالي <x-icon name="arrow-right" class="size-4 rotate-180" /></span>
        @endif
    </nav>
@endif
