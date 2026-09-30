@forelse ($products as $product)
    <div class="flex items-center gap-3 border-t border-gray-100 py-2 text-sm first:border-0">
        <x-admin.thumb :url="$product->thumbnailUrl()" alt="" size="size-9" />
        <span class="min-w-0 flex-1 truncate">{{ $product->name }}</span>
        <span class="shrink-0 text-xs text-gray-500">حد {{ \App\Support\Decimal::trim($product->low_stock_threshold) }}</span>
        <span class="shrink-0 rounded-lg bg-red-50 px-2 py-0.5 font-bold text-red-700"><bdi dir="ltr">{{ \App\Support\Decimal::trim($product->stock_quantity) }}</bdi> {{ $product->unit->label() }}</span>
        <a href="{{ route('admin.products.edit', $product) }}" wire:navigate class="btn-ghost min-h-8 px-2" aria-label="تعديل {{ $product->name }}"><x-icon name="edit" class="size-4" /></a>
    </div>
@empty
    <p class="text-sm text-gray-500">{{ $empty }}</p>
@endforelse
