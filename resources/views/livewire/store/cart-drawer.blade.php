<div>
    @if ($open && $summary)
        <div class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="cart-drawer-title"
             x-data x-init="$nextTick(() => $refs.close.focus())" @keydown.escape.window="$wire.close()">
            <div class="absolute inset-0 bg-black/40" wire:click="close"></div>

            <div class="absolute inset-x-0 bottom-0 flex max-h-[85vh] flex-col rounded-t-3xl bg-white shadow-2xl md:inset-y-0 md:start-auto md:end-0 md:max-h-none md:w-96 md:rounded-none">
                <div class="flex items-center justify-between border-b border-gray-100 p-4">
                    <h2 id="cart-drawer-title" class="flex items-center gap-2 text-lg font-bold">
                        <x-icon name="check" class="size-6 rounded-full bg-green-100 p-1 text-green-700" /> تمت الإضافة للسلة
                    </h2>
                    <button type="button" x-ref="close" wire:click="close" class="rounded-lg p-2 hover:bg-gray-100" aria-label="إغلاق السلة"><x-icon name="close" /></button>
                </div>

                <div class="flex-1 divide-y divide-gray-100 overflow-y-auto px-4">
                    @foreach ($summary->lines as $line)
                        <div wire:key="drawer-{{ $line->item->id }}" @class(['flex items-center gap-3 py-3', 'bg-brand-50 -mx-4 px-4' => $line->item->id === $lastAddedItemId])>
                            <x-admin.thumb :url="$line->product->thumbnailUrl()" :alt="$line->product->name" size="size-14" />
                            <div class="min-w-0 flex-1">
                                <div class="line-clamp-1 text-sm font-bold">{{ $line->product->name }}</div>
                                <div class="text-xs text-gray-600">
                                    <bdi dir="ltr">{{ $line->quantity }}</bdi> {{ $line->product->unit->label() }} ×
                                    <bdi dir="ltr">{{ \App\Support\Money::short($line->price->finalPrice) }}</bdi>
                                </div>
                                @if ($line->issue)<div class="text-xs font-bold text-red-600">{{ $line->issue }}</div>@endif
                            </div>
                            <div class="text-sm font-bold"><bdi dir="ltr">{{ \App\Support\Money::format($line->lineTotal()) }}</bdi></div>
                            <button type="button" wire:click="remove({{ $line->item->id }})" class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" aria-label="حذف {{ $line->product->name }}">
                                <x-icon name="trash" class="size-5" />
                            </button>
                        </div>
                    @endforeach
                </div>

                <div class="space-y-3 border-t border-gray-100 p-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
                    <div class="flex items-center justify-between font-bold">
                        <span>إجمالي المنتجات</span>
                        <bdi dir="ltr" class="text-lg">{{ \App\Support\Money::format($summary->subtotal()) }}</bdi>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" wire:click="close" class="btn-secondary">متابعة التسوق</button>
                        <a href="{{ route('cart') }}" wire:navigate class="btn-primary">الذهاب للسلة</a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
