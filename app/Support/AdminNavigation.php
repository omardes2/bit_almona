<?php

namespace App\Support;

use Illuminate\Support\Facades\Gate;

final class AdminNavigation
{
    /**
     * Sidebar items. Items with a null route are shown as "قريبًا".
     *
     * @return list<array{label: string, route: ?string, active: string, icon: string, gate?: string}>
     */
    public static function items(): array
    {
        $canManageCatalog = Gate::allows('manage-catalog');
        $gates = [
            'catalog' => $canManageCatalog,
            'orders' => Gate::allows('manage-orders'),
            'delivery' => Gate::allows('manage-delivery'),
            'settings' => Gate::allows('manage-settings'),
        ];

        $items = [
            ['label' => 'الرئيسية', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'home'],
            ['label' => 'الطلبات', 'route' => 'admin.orders.index', 'active' => 'admin.orders.*', 'icon' => 'orders', 'gate' => 'orders'],
            ['label' => 'المنتجات', 'route' => 'admin.products.index', 'active' => 'admin.products.*', 'icon' => 'products', 'gate' => 'catalog'],
            ['label' => 'الأقسام', 'route' => 'admin.categories.index', 'active' => 'admin.categories.*', 'icon' => 'categories', 'gate' => 'catalog'],
            ['label' => 'العروض', 'route' => 'admin.offers.index', 'active' => 'admin.offers.*', 'icon' => 'offers', 'gate' => 'catalog'],
            ['label' => 'البنرات', 'route' => 'admin.banners.index', 'active' => 'admin.banners.*', 'icon' => 'banners', 'gate' => 'catalog'],
            ['label' => 'العملاء', 'route' => null, 'active' => 'admin.customers.*', 'icon' => 'customers'],
            ['label' => 'مناطق التوصيل', 'route' => 'admin.delivery-zones.index', 'active' => 'admin.delivery-zones.*', 'icon' => 'zones', 'gate' => 'delivery'],
            ['label' => 'الإعدادات', 'route' => 'admin.settings', 'active' => 'admin.settings', 'icon' => 'settings', 'gate' => 'settings'],
        ];

        return array_values(array_filter($items, fn ($item) => ! isset($item['gate']) || $gates[$item['gate']]));
    }
}
