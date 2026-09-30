<div wire:poll.60s.visible x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative">
    <button type="button" @click="open = !open" :aria-expanded="open" class="relative rounded-lg p-2 text-gray-700 hover:bg-gray-100"
            aria-label="الإشعارات{{ $unread ? '، '.$unread.' غير مقروء' : '' }}">
        <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
        @if ($unread)
            <span class="absolute -top-0.5 -start-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-bold text-white">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition.origin.top class="absolute end-0 z-50 mt-2 w-80 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-gray-200">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <span class="font-bold">الإشعارات</span>
            <a href="{{ route('admin.notifications') }}" wire:navigate class="text-sm font-bold text-brand-700">عرض الكل</a>
        </div>
        @forelse ($latest as $n)
            <button type="button" wire:key="bell-{{ $n->id }}" wire:click="open('{{ $n->id }}')" @class(['flex w-full items-start gap-3 border-b border-gray-50 px-4 py-3 text-start hover:bg-gray-50', 'bg-brand-50/60' => ! $n->read_at])>
                <x-admin.notification-icon :data="$n->data" />
                <span class="min-w-0 flex-1">
                    <span class="line-clamp-1 text-sm font-bold">{{ $n->data['title'] ?? '' }}</span>
                    <span class="line-clamp-1 text-xs text-gray-600">{{ $n->data['body'] ?? '' }}</span>
                    <span class="text-[11px] text-gray-400">{{ $n->created_at->diffForHumans() }}</span>
                </span>
                @unless ($n->read_at)<span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-600" aria-label="غير مقروء"></span>@endunless
            </button>
        @empty
            <p class="px-4 py-6 text-center text-sm text-gray-500">لا توجد إشعارات.</p>
        @endforelse
    </div>
</div>
