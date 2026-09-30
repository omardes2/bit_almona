<div>
    <x-admin.page-header title="الإشعارات" :subtitle="$unread.' غير مقروء'">
        @if ($unread)
            <x-slot:actions><button type="button" wire:click="markAllRead" class="btn-secondary"><x-icon name="check" class="size-4" /> تعليم الكل كمقروء</button></x-slot:actions>
        @endif
    </x-admin.page-header>

    <div class="mb-4 flex gap-2">
        @foreach (['all' => 'الكل', 'unread' => 'غير المقروءة'] as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')" @class(['btn min-h-9 py-1.5', 'bg-gray-900 text-white' => $filter === $key, 'bg-white text-gray-700 ring-1 ring-gray-300' => $filter !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($notifications->isEmpty())
        <x-admin.empty-state icon="alert" title="لا توجد إشعارات" description="ستظهر هنا الطلبات الجديدة وتنبيهات المخزون." />
    @else
        <div class="card divide-y divide-gray-100">
            @foreach ($notifications as $n)
                <div wire:key="n-{{ $n->id }}" @class(['flex items-start gap-3 p-3 sm:p-4', 'bg-brand-50/50' => ! $n->read_at])>
                    <x-admin.notification-icon :data="$n->data" />
                    <button type="button" wire:click="open('{{ $n->id }}')" class="min-w-0 flex-1 text-start">
                        <span class="block font-bold">{{ $n->data['title'] ?? '' }}</span>
                        <span class="block text-sm text-gray-600">{{ $n->data['body'] ?? '' }}</span>
                        <span class="text-xs text-gray-400"><bdi dir="ltr">{{ $n->created_at->format('Y-m-d H:i') }}</bdi> · {{ $n->created_at->diffForHumans() }}</span>
                    </button>
                    @if ($n->read_at)
                        <button type="button" wire:click="markUnread('{{ $n->id }}')" class="btn-ghost min-h-9 px-2 text-xs">غير مقروء</button>
                    @else
                        <button type="button" wire:click="markRead('{{ $n->id }}')" class="btn-ghost min-h-9 px-2 text-xs">مقروء</button>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $notifications->links('components.admin.pagination') }}</div>
    @endif
</div>
