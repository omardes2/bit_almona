<div>
    <x-admin.page-header :title="'طلب '.$order->order_number" :subtitle="$order->created_at->format('Y-m-d H:i')" :back="route('admin.orders.index')">
        <x-slot:actions><x-order-status-badge :status="$order->status" class="px-3 py-1 text-sm" /></x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Status actions --}}
            @php($next = $order->status->allowedTransitions())
            @if ($next !== [])
                <section class="card space-y-3 p-4" x-data="{ cancelling: false }">
                    <h2 class="font-bold">تغيير الحالة</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($next as $target)
                            @if ($target === \App\Enums\OrderStatus::Cancelled)
                                <button type="button" @click="cancelling = !cancelling" class="btn-secondary text-red-600">{{ $target->actionLabel() }}</button>
                            @else
                                <button type="button" wire:click="changeStatus('{{ $target->value }}')" wire:loading.attr="disabled"
                                        wire:confirm="تغيير حالة الطلب إلى «{{ $target->label() }}»؟" class="btn-primary">{{ $target->actionLabel() }}</button>
                            @endif
                        @endforeach
                    </div>
                    <div x-show="cancelling" x-cloak class="space-y-2 rounded-xl bg-red-50 p-3">
                        <x-admin.field label="سبب الإلغاء" for="cancellationReason" error="cancellationReason" required>
                            <textarea id="cancellationReason" wire:model="cancellationReason" rows="2" class="form-input" placeholder="مثال: الزبون طلب الإلغاء"></textarea>
                        </x-admin.field>
                        <p class="text-xs text-red-700">عند الإلغاء تُعاد كميات المنتجات إلى المخزون مرة واحدة.</p>
                        <button type="button" wire:click="changeStatus('cancelled')" wire:confirm="تأكيد إلغاء الطلب؟" class="btn-danger">تأكيد الإلغاء</button>
                    </div>
                </section>
            @endif

            {{-- Items --}}
            <section class="card p-4">
                <h2 class="mb-3 font-bold">المنتجات ({{ $order->items->count() }})</h2>
                <div class="divide-y divide-gray-100">
                    @foreach ($order->items as $item)
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <div class="min-w-0">
                                <div class="font-medium">{{ $item->product_name }}</div>
                                <div class="text-xs text-gray-500">
                                    <bdi dir="ltr">{{ \App\Support\Decimal::trim($item->quantity) }}</bdi> {{ $item->unit->label() }} ×
                                    <bdi dir="ltr">{{ \App\Support\Money::format($item->unit_price) }}</bdi>
                                    @if ($item->product_sku) · <bdi dir="ltr">{{ $item->product_sku }}</bdi>@endif
                                    @if ((float) $item->original_unit_price > (float) $item->unit_price)
                                        · <del><bdi dir="ltr">{{ \App\Support\Money::format($item->original_unit_price) }}</bdi></del>
                                    @endif
                                </div>
                            </div>
                            <div class="shrink-0 font-bold"><bdi dir="ltr">{{ \App\Support\Money::format($item->line_total) }}</bdi></div>
                        </div>
                    @endforeach
                </div>
                @include('store.partials.order-totals', ['order' => $order])
            </section>

            {{-- History --}}
            <section class="card p-4">
                <h2 class="mb-3 font-bold">سجل الحالات</h2>
                @include('store.partials.order-timeline', ['order' => $order, 'showActor' => true])
            </section>
        </div>

        <div class="space-y-4">
            <section class="card space-y-2 p-4 text-sm">
                <h2 class="font-bold">العميل</h2>
                <div>{{ $order->customer_name }}</div>
                <div class="flex flex-wrap gap-2">
                    <a href="tel:{{ $order->customer_phone }}" class="btn-secondary min-h-9 py-1.5"><x-icon name="phone" class="size-4" /> <bdi dir="ltr">{{ $order->customer_phone }}</bdi></a>
                    @if ($order->customer_whatsapp)
                        <a href="https://wa.me/970{{ substr($order->customer_whatsapp, 1) }}" target="_blank" rel="noopener" class="btn-secondary min-h-9 py-1.5"><x-icon name="chat" class="size-4" /> واتساب</a>
                    @endif
                </div>
            </section>

            <section class="card space-y-1 p-4 text-sm">
                <h2 class="mb-1 font-bold">التوصيل</h2>
                <div>المستلم: {{ $order->recipient_name }} · <bdi dir="ltr">{{ $order->recipient_phone }}</bdi></div>
                <div>المنطقة: <b>{{ $order->delivery_zone_name }}</b></div>
                <div>{{ collect([$order->delivery_city, $order->delivery_area])->filter()->implode('، ') }}</div>
                <div class="whitespace-pre-line text-gray-700">{{ $order->delivery_address }}</div>
                @if ($order->customer_notes)
                    <div class="mt-2 rounded-xl bg-amber-50 p-2 text-amber-900"><b>ملاحظات الزبون:</b> {{ $order->customer_notes }}</div>
                @endif
            </section>

            <section class="card space-y-2 p-4 text-sm">
                <h2 class="font-bold">الدفع</h2>
                <div>{{ $order->payment_method->label() }}</div>
                @if ($order->payment)
                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-bold {{ $order->payment->status->badgeClasses() }}">{{ $order->payment->status->label() }}</span>
                    @if ($order->payment->paid_at)<div class="text-xs text-gray-500">دُفع في <bdi dir="ltr">{{ $order->payment->paid_at->format('Y-m-d H:i') }}</bdi></div>@endif
                    @if ($order->payment->status === \App\Enums\PaymentStatus::Pending && $order->status !== \App\Enums\OrderStatus::Cancelled)
                        <button type="button" wire:click="markPaid" wire:confirm="تأكيد استلام مبلغ {{ \App\Support\Money::format($order->total) }} نقدًا؟" class="btn-secondary w-full">
                            <x-icon name="check" class="size-4" /> تم استلام المبلغ
                        </button>
                    @endif
                @endif
            </section>

            <section class="card space-y-2 p-4 text-sm">
                <h2 class="font-bold">ملاحظات داخلية</h2>
                <textarea wire:model="adminNotes" rows="3" class="form-input" placeholder="لا يراها الزبون"></textarea>
                <button type="button" wire:click="saveNotes" class="btn-secondary w-full">حفظ الملاحظات</button>
            </section>
        </div>
    </div>
</div>
