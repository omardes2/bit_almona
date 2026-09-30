<ol class="relative space-y-4 border-s-2 border-gray-200 ps-5">
    @foreach ($order->statusHistory as $entry)
        <li class="relative">
            <span class="absolute -start-[1.72rem] top-1 size-3 rounded-full ring-4 ring-white {{ $loop->last ? 'bg-brand-600' : 'bg-gray-300' }}"></span>
            <div class="flex flex-wrap items-center gap-2">
                <x-order-status-badge :status="$entry->to_status" />
                <span class="text-xs text-gray-500"><bdi dir="ltr">{{ $entry->created_at?->format('Y-m-d H:i') }}</bdi></span>
            </div>
            @if ($entry->note)<p class="mt-1 text-sm text-gray-600">{{ $entry->note }}</p>@endif
            @if (! empty($showActor) && $entry->changedBy)<p class="text-xs text-gray-400">بواسطة {{ $entry->changedBy->name }}</p>@endif
        </li>
    @endforeach
</ol>
