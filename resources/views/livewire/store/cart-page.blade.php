<div>
    <h1 class="mb-4 text-2xl font-extrabold">سلة المشتريات</h1>

    @foreach ($notices as $notice)
        <div class="mb-2 flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200" role="status">
            <x-icon name="alert" class="size-5 shrink-0" /> {{ $notice }}
        </div>
    @endforeach

    @if ($summary->isEmpty())
        <div class="card flex flex-col items-center p-10 text-center">
            <x-icon name="cart" class="mb-3 size-14 text-gray-300" />
            <h2 class="text-lg font-bold">سلتك فارغة</h2>
            <p class="mt-1 text-sm text-gray-500">أضف منتجات من الأقسام أو العروض.</p>
            <a href="{{ route('home') }}" wire:navigate class="btn-primary mt-5">ابدأ التسوق</a>
        </div>
    @else
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-2 lg:col-span-2">
                @foreach ($summary->lines as $line)
                    @php($product = $line->product)
                    <div wire:key="line-{{ $line->item->id }}" @class(['card flex gap-3 p-3', 'opacity-75' => ! $line->purchasable])>
                        @if ($product->isVisibleInStore())
                            <a href="{{ $product->url() }}" wire:navigate class="shrink-0"><x-admin.thumb :url="$product->thumbnailUrl()" :alt="$product->name" size="size-20" /></a>
                        @else
                            <x-admin.thumb :url="$product->thumbnailUrl()" :alt="$product->name" size="size-20" />
                        @endif

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="line-clamp-2 font-bold leading-snug">{{ $product->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $product->unit->label() }}</div>
                                </div>
                                <button type="button" wire:click="remove({{ $line->item->id }})" wire:confirm="حذف «{{ $product->name }}» من السلة؟"
                                        class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" aria-label="حذف {{ $product->name }} من السلة">
                                    <x-icon name="trash" class="size-5" />
                                </button>
                            </div>

                            @if ($line->issue)
                                <p class="mt-1 flex items-center gap-1 text-sm font-bold text-red-600"><x-icon name="alert" class="size-4" /> {{ $line->issue }}</p>
                            @else
                                <x-store.price :price="$line->price" size="sm" class="mt-1" />
                            @endif

                            <div class="mt-2 flex items-center justify-between gap-2">
                                @if ($line->purchasable)
                                    <div class="flex items-center rounded-xl ring-1 ring-gray-300" role="group" aria-label="كمية {{ $product->name }}">
                                        <button type="button" wire:click="increment({{ $line->item->id }})" @disabled(! $line->canIncrement())
                                                class="flex size-10 items-center justify-center rounded-s-xl text-gray-700 hover:bg-gray-100 disabled:opacity-40" aria-label="زيادة الكمية">
                                            <x-icon name="plus" />
                                        </button>
                                        <span class="min-w-12 text-center font-bold" aria-live="polite"><bdi dir="ltr">{{ $line->quantity }}</bdi></span>
                                        <button type="button" wire:click="decrement({{ $line->item->id }})" @disabled(! $line->canDecrement())
                                                class="flex size-10 items-center justify-center rounded-e-xl text-gray-700 hover:bg-gray-100 disabled:opacity-40" aria-label="تقليل الكمية">
                                            <x-icon name="minus" />
                                        </button>
                                    </div>
                                    <div class="font-extrabold"><bdi dir="ltr">{{ \App\Support\Money::format($line->lineTotal()) }}</bdi></div>
                                @else
                                    <span class="text-sm text-gray-500">لن يُحسب في الإجمالي</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <aside class="card h-fit space-y-3 p-4 lg:sticky lg:top-24">
                <h2 class="font-bold">ملخص السلة</h2>
                <div class="flex items-center justify-between">
                    <span class="text-gray-600">إجمالي المنتجات ({{ $summary->purchasableCount() }})</span>
                    <bdi dir="ltr" class="text-xl font-extrabold">{{ \App\Support\Money::format($summary->subtotal()) }}</bdi>
                </div>
                <p class="flex items-center gap-2 rounded-xl bg-gray-50 p-3 text-sm text-gray-600">
                    <x-icon name="truck" class="size-5 shrink-0" /> التوصيل يُحسب عند إتمام الطلب
                </p>
                @if ($summary->purchasableCount() > 0)
                    <a href="{{ route('checkout') }}" class="btn-primary w-full py-3 text-base">متابعة لإتمام الطلب</a>
                    @guest
                        <p class="text-center text-xs text-gray-500">ستحتاج لتسجيل الدخول أو إنشاء حساب — سلتك محفوظة.</p>
                    @endguest
                @else
                    <button type="button" disabled class="btn w-full bg-gray-200 text-gray-600">لا توجد منتجات متوفرة لإتمام الطلب</button>
                @endif
                <a href="{{ route('home') }}" wire:navigate class="btn-secondary w-full">متابعة التسوق</a>
            </aside>
        </div>
    @endif
</div>
