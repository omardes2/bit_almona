@if ($product->trashed())
    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">محذوف</span>
@else
    <x-admin.status-badge :status="$product->status" />
@endif
@if ($product->activeOffer)
    <span class="rounded-full bg-brand-600 px-2 py-0.5 text-xs font-bold text-white">عليه عرض <bdi dir="ltr">-{{ $product->activeOffer->discountPercentage() }}%</bdi></span>
@endif
@if ($product->is_featured)
    <span class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-bold text-yellow-800">مميز</span>
@endif
@if (empty($hideStock))
    <span @class(['rounded-full px-2 py-0.5 text-xs', 'bg-red-50 font-bold text-red-700' => $product->isLowStock(), 'bg-gray-100 text-gray-600' => ! $product->isLowStock()])>
        المخزون: {{ \App\Support\Decimal::trim($product->stock_quantity) }} {{ $product->unit->label() }}
    </span>
@elseif ($product->isLowStock())
    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-bold text-red-700">مخزون منخفض</span>
@endif
