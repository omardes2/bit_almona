<div class="mx-auto max-w-2xl">
    @include('account.partials.nav', ['active' => 'orders'])
    <h1 class="mb-4 text-2xl font-extrabold">طلباتي</h1>

    @if ($orders->isEmpty())
        <div class="card p-8 text-center">
            <x-icon name="orders" class="mx-auto mb-3 size-12 text-gray-300" />
            <p class="font-bold">لا توجد طلبات بعد</p>
            <a href="{{ route('home') }}" wire:navigate class="btn-primary mt-4">ابدأ التسوق</a>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($orders as $order)
                <a href="{{ route('account.orders.show', $order) }}" wire:navigate wire:key="order-{{ $order->id }}" class="card block p-4 hover:ring-brand-500">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-bold" dir="ltr">{{ $order->order_number }}</div>
                            <div class="text-sm text-gray-500"><bdi dir="ltr">{{ $order->created_at->format('Y-m-d H:i') }}</bdi> · {{ $order->items_count }} منتج</div>
                        </div>
                        <x-order-status-badge :status="$order->status" />
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-sm text-gray-600">الإجمالي</span>
                        <span class="text-lg font-extrabold"><bdi dir="ltr">{{ \App\Support\Money::format($order->total) }}</bdi></span>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $orders->links('components.admin.pagination') }}</div>
    @endif
</div>
