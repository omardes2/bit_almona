<div>
    <x-admin.page-header title="الطلبات" :subtitle="$orders->total().' طلب'" />

    <div class="mb-4 space-y-2">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute inset-y-0 start-3 my-auto text-gray-400" />
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="رقم الطلب، اسم الزبون أو رقم الهاتف..." class="form-input ps-10" aria-label="بحث في الطلبات">
        </div>

        <div class="flex gap-2 overflow-x-auto pb-1">
            <button type="button" wire:click="$set('status', '')" @class(['btn min-h-9 shrink-0 py-1.5', 'bg-gray-900 text-white' => $status === '', 'bg-white text-gray-700 ring-1 ring-gray-300' => $status !== ''])>
                الكل <span class="text-xs opacity-70">{{ $counts->sum() }}</span>
            </button>
            @foreach ($statuses as $s)
                <button type="button" wire:click="$set('status', '{{ $s->value }}')" @class(['btn min-h-9 shrink-0 py-1.5', 'bg-gray-900 text-white' => $status === $s->value, 'bg-white text-gray-700 ring-1 ring-gray-300' => $status !== $s->value])>
                    {{ $s->label() }} <span class="text-xs opacity-70">{{ $counts[$s->value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
            <label class="text-sm text-gray-600 sm:flex sm:items-center sm:gap-2">من <input type="date" wire:model.live="from" class="form-input mt-1 sm:mt-0 sm:w-44"></label>
            <label class="text-sm text-gray-600 sm:flex sm:items-center sm:gap-2">إلى <input type="date" wire:model.live="to" class="form-input mt-1 sm:mt-0 sm:w-44"></label>
            @if ($search !== '' || $status !== '' || $from !== '' || $to !== '')
                <button type="button" wire:click="clearFilters" class="btn-ghost col-span-2 text-sm">مسح الفلاتر</button>
            @endif
        </div>
    </div>

    <div wire:loading.class="opacity-60" wire:target="search,status,from,to,gotoPage,nextPage,previousPage">
        @if ($orders->isEmpty())
            <x-admin.empty-state icon="orders" title="لا توجد طلبات" description="ستظهر هنا الطلبات فور وصولها من المتجر." />
        @else
            {{-- Mobile cards --}}
            <div class="space-y-2 md:hidden">
                @foreach ($orders as $order)
                    <a href="{{ route('admin.orders.show', $order) }}" wire:navigate wire:key="m-order-{{ $order->id }}" class="card block p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-bold" dir="ltr">{{ $order->order_number }}</div>
                                <div class="text-sm">{{ $order->customer_name }} · <bdi dir="ltr">{{ $order->customer_phone }}</bdi></div>
                            </div>
                            <x-order-status-badge :status="$order->status" />
                        </div>
                        <div class="mt-2 flex items-center justify-between text-sm">
                            <span class="text-gray-500"><bdi dir="ltr">{{ $order->created_at->format('Y-m-d H:i') }}</bdi></span>
                            <span class="font-extrabold"><bdi dir="ltr">{{ \App\Support\Money::format($order->total) }}</bdi></span>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            {{ $order->payment_method->label() }} ·
                            @if ($order->payment)<span class="rounded-full px-1.5 {{ $order->payment->status->badgeClasses() }}">{{ $order->payment->status->label() }}</span>@endif
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Desktop table --}}
            <div class="card hidden overflow-x-auto md:block">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">رقم الطلب</th>
                            <th class="px-3 py-3 text-start font-medium">العميل</th>
                            <th class="px-3 py-3 text-start font-medium">التاريخ</th>
                            <th class="px-3 py-3 text-start font-medium">الإجمالي</th>
                            <th class="px-3 py-3 text-start font-medium">الدفع</th>
                            <th class="px-3 py-3 text-start font-medium">الحالة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($orders as $order)
                            <tr wire:key="d-order-{{ $order->id }}" class="hover:bg-gray-50/60">
                                <td class="px-4 py-3"><a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="font-bold text-brand-700 hover:underline" dir="ltr">{{ $order->order_number }}</a></td>
                                <td class="px-3 py-3">{{ $order->customer_name }}<div class="text-xs text-gray-500" dir="ltr">{{ $order->customer_phone }}</div></td>
                                <td class="px-3 py-3 text-gray-600"><bdi dir="ltr">{{ $order->created_at->format('Y-m-d H:i') }}</bdi></td>
                                <td class="px-3 py-3 font-bold"><bdi dir="ltr">{{ \App\Support\Money::format($order->total) }}</bdi></td>
                                <td class="px-3 py-3">
                                    <div class="text-xs">{{ $order->payment_method->label() }}</div>
                                    @if ($order->payment)<span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $order->payment->status->badgeClasses() }}">{{ $order->payment->status->label() }}</span>@endif
                                </td>
                                <td class="px-3 py-3"><x-order-status-badge :status="$order->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $orders->links('components.admin.pagination') }}</div>
        @endif
    </div>
</div>
