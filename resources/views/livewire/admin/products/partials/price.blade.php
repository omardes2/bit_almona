@php($offer = $product->activeOffer)
@if ($offer)
    <span class="font-bold text-brand-700">{{ \App\Support\Money::format($offer->offer_price) }}</span>
    <span class="text-xs text-gray-400 line-through">{{ \App\Support\Money::format($product->sale_price) }}</span>
@else
    <span class="font-bold">{{ \App\Support\Money::format($product->sale_price) }}</span>
    @if ((float) $product->original_price > (float) $product->sale_price)
        <span class="text-xs text-gray-400 line-through">{{ \App\Support\Money::format($product->original_price) }}</span>
    @endif
@endif
