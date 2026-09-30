<div>
    <x-admin.page-header title="الرئيسية" subtitle="نظرة سريعة على المتجر">
        @if ($canManageCatalog)
            <x-slot:actions>
                <a href="{{ route('admin.products.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" /> إضافة منتج
                </a>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @php
        $ordersUrl = fn ($status = null) => $canManageOrders ? route('admin.orders.index', array_filter(['status' => $status])) : null;
        $cards = [
            ['label' => 'طلبات اليوم', 'value' => number_format($ordersToday), 'color' => 'text-gray-900', 'url' => $ordersUrl()],
            ['label' => 'مبيعات اليوم', 'value' => \App\Support\Money::format($salesToday), 'color' => 'text-brand-700'],
            ['label' => 'طلبات جديدة', 'value' => number_format((int) ($openByStatus['new'] ?? 0)), 'color' => 'text-blue-700', 'url' => $ordersUrl('new')],
            ['label' => 'قيد التجهيز', 'value' => number_format((int) ($openByStatus['preparing'] ?? 0)), 'color' => 'text-amber-700', 'url' => $ordersUrl('preparing')],
            ['label' => 'خرجت للتوصيل', 'value' => number_format((int) ($openByStatus['out_for_delivery'] ?? 0)), 'color' => 'text-purple-700', 'url' => $ordersUrl('out_for_delivery')],
            ['label' => 'كل المنتجات', 'value' => number_format((int) $products->total), 'color' => 'text-gray-900', 'url' => $canManageCatalog ? route('admin.products.index') : null],
            ['label' => 'منتجات متاحة', 'value' => number_format((int) $products->available), 'color' => 'text-green-700', 'url' => $canManageCatalog ? route('admin.products.index', ['status' => 'available']) : null],
            ['label' => 'غير متاحة', 'value' => number_format((int) $products->unavailable), 'color' => 'text-amber-700', 'url' => $canManageCatalog ? route('admin.products.index', ['status' => 'unavailable']) : null],
            ['label' => 'مخزون منخفض', 'value' => number_format((int) $products->low_stock), 'color' => 'text-red-600', 'url' => $canManageCatalog ? route('admin.products.index', ['lowStock' => 1]) : null],
            ['label' => 'الأقسام', 'value' => number_format($categoriesCount), 'color' => 'text-gray-900', 'url' => $canManageCatalog ? route('admin.categories.index') : null],
            ['label' => 'عروض فعالة الآن', 'value' => number_format($runningOffers), 'color' => 'text-brand-700', 'url' => $canManageCatalog ? route('admin.offers.index', ['status' => 'running']) : null],
            ['label' => 'بنرات فعالة الآن', 'value' => number_format($runningBanners), 'color' => 'text-brand-700', 'url' => $canManageCatalog ? route('admin.banners.index', ['status' => 'running']) : null],
        ];
    @endphp

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-5">
        @foreach ($cards as $card)
            @if (! empty($card['url']))
                <a href="{{ $card['url'] }}" wire:navigate class="card block p-4 transition hover:ring-brand-500">
            @else
                <div class="card p-4">
            @endif
                <div class="text-sm text-gray-500">{{ $card['label'] }}</div>
                <div class="mt-1 text-2xl font-bold {{ $card['color'] }}">{{ $card['value'] }}</div>
            @if (! empty($card['url'])) </a> @else </div> @endif
        @endforeach
    </div>

    <section class="card mt-6 p-4 sm:p-5">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="flex items-center gap-2 font-bold">
                <x-icon name="alert" class="text-red-500" /> منتجات منخفضة المخزون
            </h2>
            @if ($canManageCatalog && $lowStockProducts->isNotEmpty())
                <a href="{{ route('admin.products.index', ['lowStock' => 1]) }}" wire:navigate class="text-sm font-medium text-brand-700">عرض الكل</a>
            @endif
        </div>

        @forelse ($lowStockProducts as $product)
            <div class="flex items-center gap-3 border-t border-gray-100 py-3 first-of-type:border-t-0">
                <x-admin.thumb :url="$product->thumbnailUrl()" :alt="$product->name" size="size-11" />
                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium">{{ $product->name }}</div>
                    <div class="text-xs text-gray-500">الحد الأدنى: {{ (float) $product->low_stock_threshold }} {{ $product->unit->label() }}</div>
                </div>
                <span class="rounded-lg bg-red-50 px-2 py-1 text-sm font-bold text-red-700">{{ (float) $product->stock_quantity }}</span>
                @if ($canManageCatalog)
                    <a href="{{ route('admin.products.edit', $product) }}" wire:navigate class="btn-ghost" aria-label="تعديل">
                        <x-icon name="edit" />
                    </a>
                @endif
            </div>
        @empty
            <p class="py-4 text-center text-sm text-gray-500">لا توجد منتجات منخفضة المخزون 👌</p>
        @endforelse
    </section>
</div>
