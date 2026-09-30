<?php

namespace App\Support;

use Illuminate\Support\Facades\Gate;

final class AdminNavigation
{
    /**
     * Sidebar items. Items with a null route are shown as "قريبًا".
     *
     * @return list<array{label: string, route: ?string, active: string, icon: string}>
     */
    public static function items(): array
    {
        $canManageCatalog = Gate::allows('manage-catalog');

        $items = [
            ['label' => 'الرئيسية', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'home'],
            ['label' => 'الطلبات', 'route' => null, 'active' => 'admin.orders.*', 'icon' => 'orders'],
            ['label' => 'المنتجات', 'route' => 'admin.products.index', 'active' => 'admin.products.*', 'icon' => 'products', 'catalog' => true],
            ['label' => 'الأقسام', 'route' => 'admin.categories.index', 'active' => 'admin.categories.*', 'icon' => 'categories', 'catalog' => true],
            ['label' => 'العروض', 'route' => 'admin.offers.index', 'active' => 'admin.offers.*', 'icon' => 'offers', 'catalog' => true],
            ['label' => 'البنرات', 'route' => 'admin.banners.index', 'active' => 'admin.banners.*', 'icon' => 'banners', 'catalog' => true],
            ['label' => 'العملاء', 'route' => null, 'active' => 'admin.customers.*', 'icon' => 'customers'],
            ['label' => 'مناطق التوصيل', 'route' => null, 'active' => 'admin.delivery-zones.*', 'icon' => 'zones'],
            ['label' => 'الإعدادات', 'route' => null, 'active' => 'admin.settings.*', 'icon' => 'settings'],
        ];

        return array_values(array_filter($items, fn ($item) => empty($item['catalog']) || $canManageCatalog));
    }
}
