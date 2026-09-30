<dl class="mt-3 space-y-1.5 border-t border-gray-100 pt-3 text-sm">
    <div class="flex justify-between"><dt class="text-gray-600">المنتجات</dt><dd><bdi dir="ltr">{{ \App\Support\Money::format($order->subtotal) }}</bdi></dd></div>
    @if ((float) $order->discount_total > 0)
        <div class="flex justify-between text-red-600"><dt>الخصم</dt><dd><bdi dir="ltr">- {{ \App\Support\Money::format($order->discount_total) }}</bdi></dd></div>
    @endif
    <div class="flex justify-between"><dt class="text-gray-600">التوصيل ({{ $order->delivery_zone_name }})</dt><dd><bdi dir="ltr">{{ \App\Support\Money::format($order->delivery_fee) }}</bdi></dd></div>
    <div class="flex justify-between border-t border-gray-100 pt-2 text-base font-extrabold"><dt>الإجمالي</dt><dd><bdi dir="ltr">{{ \App\Support\Money::format($order->total) }}</bdi></dd></div>
</dl>
