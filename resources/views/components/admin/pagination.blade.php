{{-- Mobile-friendly RTL pagination for Livewire paginators. --}}
@php($pageName = $paginator->getPageName())
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="التنقل بين الصفحات" class="flex items-center justify-between gap-2">
        <button type="button" wire:click="previousPage('{{ $pageName }}')" wire:loading.attr="disabled"
                @disabled($paginator->onFirstPage()) class="btn-secondary">
            <x-admin.icon name="arrow-right" class="size-4" /> السابق
        </button>

        <div class="flex items-center gap-1 text-sm text-gray-600">
            @php($start = max(1, $paginator->currentPage() - 2))
            @php($end = min($paginator->lastPage(), $paginator->currentPage() + 2))
            @for ($page = $start; $page <= $end; $page++)
                <button type="button" wire:key="page-{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')"
                        @class(['hidden size-10 rounded-xl text-sm font-bold sm:inline-flex sm:items-center sm:justify-center', 'bg-brand-600 text-white' => $page === $paginator->currentPage(), 'bg-white ring-1 ring-gray-300 hover:bg-gray-50' => $page !== $paginator->currentPage()])
                        aria-label="انتقل إلى الصفحة {{ $page }}">{{ $page }}</button>
            @endfor
            <span class="sm:hidden">صفحة {{ $paginator->currentPage() }} من {{ $paginator->lastPage() }}</span>
        </div>

        <button type="button" wire:click="nextPage('{{ $pageName }}')" wire:loading.attr="disabled"
                @disabled(! $paginator->hasMorePages()) class="btn-secondary">
            التالي <x-admin.icon name="arrow-right" class="size-4 rotate-180" />
        </button>
    </nav>
@endif
