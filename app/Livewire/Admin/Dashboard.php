<?php

namespace App\Livewire\Admin;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('لوحة التحكم')]
class Dashboard extends Component
{
    public function render()
    {
        // One aggregate query for all product counters.
        $products = Product::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as available', [ProductStatus::Available->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as unavailable', [ProductStatus::Unavailable->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as hidden', [ProductStatus::Hidden->value])
            ->selectRaw('sum(case when stock_quantity <= low_stock_threshold then 1 else 0 end) as low_stock')
            ->toBase()
            ->first();

        $ordersToday = Order::query()
            ->whereBetween('created_at', [today(), today()->endOfDay()])
            ->where('status', '!=', OrderStatus::Cancelled)
            ->selectRaw('count(*) as count, coalesce(sum(total), 0) as sales')
            ->toBase()
            ->first();

        // Open orders by status, in one grouped query.
        $openByStatus = Order::query()
            ->whereIn('status', [OrderStatus::New, OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::OutForDelivery])
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('livewire.admin.dashboard', [
            'products' => $products,
            'categoriesCount' => Category::count(),
            'runningOffers' => Offer::running()->whereHas('product')->count(),
            'runningBanners' => Banner::running()->count(),
            'ordersToday' => (int) $ordersToday->count,
            'salesToday' => (string) $ordersToday->sales,
            'lowStockProducts' => Product::query()
                ->lowStock()
                ->where('status', '!=', ProductStatus::Hidden)
                ->orderBy('stock_quantity')
                ->limit(6)
                ->get(['id', 'name', 'sku', 'main_image', 'stock_quantity', 'low_stock_threshold', 'unit']),
            'canManageCatalog' => Gate::allows('manage-catalog'),
            'canManageOrders' => Gate::allows('manage-orders'),
            'openByStatus' => $openByStatus,
        ]);
    }
}
