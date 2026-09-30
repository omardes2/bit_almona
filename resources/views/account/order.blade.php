<x-layouts::app :title="$confirmation ? 'تم استلام طلبك' : 'طلب '.$order->order_number" :noindex="true">
    <div class="mx-auto max-w-2xl">
        @if ($confirmation)
            <div class="card mb-4 p-6 text-center">
                @if ($logo = \App\Support\Store::logoUrl())
                    <img src="{{ $logo }}" alt="{{ $storeName }}" width="64" height="64" class="mx-auto mb-3 size-16 object-contain">
                @endif
                <span class="mx-auto mb-3 flex size-14 items-center justify-center rounded-full bg-green-100 text-green-700"><x-icon name="check" class="size-8" /></span>
                <h1 class="text-2xl font-extrabold">تم استلام طلبك بنجاح</h1>
                <p class="mt-2 text-gray-600">سنتواصل معك لتأكيد الطلب. شكرًا لتسوقك من {{ $storeName }}.</p>
                <p class="mt-3 text-sm">رقم الطلب: <b dir="ltr" class="text-lg">{{ $order->order_number }}</b></p>
            </div>
        @else
            @include('account.partials.nav', ['active' => 'orders'])
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-2xl font-extrabold">طلب <span dir="ltr">{{ $order->order_number }}</span></h1>
                <x-order-status-badge :status="$order->status" class="px-3 py-1 text-sm" />
            </div>
        @endif

        <section class="card mb-4 p-4">
            <div class="mb-2 flex items-center justify-between text-sm text-gray-600">
                <span>التاريخ: <bdi dir="ltr">{{ $order->created_at->format('Y-m-d H:i') }}</bdi></span>
                @if ($confirmation)<x-order-status-badge :status="$order->status" />@endif
            </div>
            <h2 class="mb-2 font-bold">المنتجات</h2>
            <div class="divide-y divide-gray-100">
                @foreach ($order->items as $item)
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <div class="font-medium">{{ $item->product_name }}</div>
                            <div class="text-xs text-gray-500">
                                <bdi dir="ltr">{{ \App\Support\Decimal::trim($item->quantity) }}</bdi> {{ $item->unit->label() }} ×
                                <bdi dir="ltr">{{ \App\Support\Money::format($item->unit_price) }}</bdi>
                            </div>
                        </div>
                        <div class="shrink-0 font-bold"><bdi dir="ltr">{{ \App\Support\Money::format($item->line_total) }}</bdi></div>
                    </div>
                @endforeach
            </div>
            @include('store.partials.order-totals', ['order' => $order])
        </section>

        <div class="grid gap-4 sm:grid-cols-2">
            <section class="card p-4 text-sm">
                <h2 class="mb-2 font-bold">عنوان التوصيل</h2>
                <div>{{ $order->recipient_name }} · <bdi dir="ltr">{{ $order->recipient_phone }}</bdi></div>
                <div>{{ collect([$order->delivery_city, $order->delivery_area])->filter()->implode('، ') }} — {{ $order->delivery_zone_name }}</div>
                <div class="whitespace-pre-line text-gray-600">{{ $order->delivery_address }}</div>
                @if ($order->customer_notes)<div class="mt-2 text-gray-600">ملاحظاتك: {{ $order->customer_notes }}</div>@endif
            </section>
            <section class="card p-4 text-sm">
                <h2 class="mb-2 font-bold">طريقة الدفع</h2>
                <div>{{ $order->payment_method->label() }}</div>
                @if ($order->payment)<div class="mt-1 text-gray-600">حالة الدفع: {{ $order->payment->status->label() }}</div>@endif
            </section>
        </div>

        @unless ($confirmation)
            <section class="card mt-4 p-4">
                <h2 class="mb-3 font-bold">مراحل الطلب</h2>
                @include('store.partials.order-timeline', ['order' => $order])
            </section>
        @endunless

        <div class="mt-4 flex flex-wrap gap-2">
            @if ($confirmation)
                <a href="{{ route('account.orders.show', $order) }}" wire:navigate class="btn-primary flex-1">متابعة الطلب</a>
            @endif
            <a href="{{ route('home') }}" wire:navigate class="btn-secondary flex-1">متابعة التسوق</a>
        </div>
    </div>
</x-layouts::app>
