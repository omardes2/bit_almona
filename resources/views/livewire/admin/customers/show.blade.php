<div>
    <x-admin.page-header :title="$customer->name" :subtitle="'عميل منذ '.$customer->created_at->format('Y-m-d')" :back="route('admin.customers.index')">
        <x-slot:actions><x-admin.status-pill :active="$customer->isActive()" class="px-3 py-1 text-sm" /></x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['عدد الطلبات', number_format((int) $stats->orders_count)],
            ['إجمالي المشتريات (مسلّمة)', \App\Support\Money::format($stats->delivered_total ?? 0)],
            ['متوسط قيمة الطلب', \App\Support\Money::format($average)],
            ['آخر طلب', $stats->last_order_at ? \Illuminate\Support\Carbon::parse($stats->last_order_at)->format('Y-m-d') : '—'],
        ] as [$label, $value])
            <div class="card p-4"><div class="text-sm text-gray-500">{{ $label }}</div><div class="mt-1 text-xl font-bold"><bdi dir="ltr">{{ $value }}</bdi></div></div>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4">
            <section class="card space-y-2 p-4 text-sm">
                <h2 class="font-bold">بيانات العميل</h2>
                <div>الاسم: <b>{{ $customer->name }}</b></div>
                <div>الجوال (رقم الدخول): <bdi dir="ltr">{{ $customer->phone }}</bdi></div>
                <div>واتساب: <bdi dir="ltr">{{ $customer->customer?->whatsapp ?? '—' }}</bdi></div>
                <div>تاريخ التسجيل: <bdi dir="ltr">{{ $customer->created_at->format('Y-m-d H:i') }}</bdi></div>
                <p class="rounded-xl bg-gray-50 p-2 text-xs text-gray-500">لا يمكن تغيير رقم الدخول من الإدارة. سيتم ذلك لاحقًا عبر التحقق برمز يُرسل للزبون.</p>
                <div class="flex flex-wrap gap-2 pt-1">
                    <a href="tel:{{ $customer->phone }}" class="btn-secondary min-h-9 py-1.5"><x-icon name="phone" class="size-4" /> اتصال</a>
                    @if ($customer->customer?->whatsapp)
                        <a href="https://wa.me/970{{ substr($customer->customer->whatsapp, 1) }}" target="_blank" rel="noopener" class="btn-secondary min-h-9 py-1.5"><x-icon name="chat" class="size-4" /> واتساب</a>
                    @endif
                </div>
            </section>

            <section class="card space-y-2 p-4 text-sm">
                <h2 class="font-bold">حالة الحساب</h2>
                @if ($customer->isActive())
                    <p class="text-gray-600">تعطيل الحساب يمنع الدخول ويسجّل خروج العميل فورًا. لا يُحذف العميل وتبقى طلباته محفوظة.</p>
                    <button type="button" wire:click="toggleStatus" wire:confirm="تعطيل حساب {{ $customer->name }}؟" class="btn-danger w-full">تعطيل الحساب</button>
                @else
                    <p class="text-red-700">الحساب موقوف ولا يستطيع العميل الدخول.</p>
                    <button type="button" wire:click="toggleStatus" wire:confirm="إعادة تفعيل حساب {{ $customer->name }}؟" class="btn-primary w-full">تفعيل الحساب</button>
                @endif
            </section>

            <section class="card p-4 text-sm">
                <h2 class="mb-2 font-bold">العناوين ({{ $customer->addresses->count() }})</h2>
                @forelse ($customer->addresses as $address)
                    <div class="border-t border-gray-100 py-2 first:border-0">
                        <div class="font-medium">{{ $address->label ?: 'عنوان' }} @if ($address->is_default)<span class="text-xs text-brand-700">(افتراضي)</span>@endif</div>
                        <div class="text-gray-600">{{ $address->toSingleLine() }}</div>
                        <div class="text-xs text-gray-500">{{ $address->recipient_name }} · <bdi dir="ltr">{{ $address->recipient_phone }}</bdi></div>
                    </div>
                @empty
                    <p class="text-gray-500">لا توجد عناوين.</p>
                @endforelse
            </section>
        </div>

        <section class="card p-4 lg:col-span-2">
            <h2 class="mb-3 font-bold">آخر الطلبات</h2>
            @forelse ($orders as $order)
                <a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="flex items-center justify-between gap-3 border-t border-gray-100 py-3 first:border-0 hover:bg-gray-50">
                    <div>
                        <div class="font-bold" dir="ltr">{{ $order->order_number }}</div>
                        <div class="text-xs text-gray-500"><bdi dir="ltr">{{ $order->created_at->format('Y-m-d H:i') }}</bdi></div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-bold"><bdi dir="ltr">{{ \App\Support\Money::format($order->total) }}</bdi></span>
                        <x-order-status-badge :status="$order->status" />
                    </div>
                </a>
            @empty
                <p class="text-sm text-gray-500">لا توجد طلبات.</p>
            @endforelse
        </section>
    </div>
</div>
