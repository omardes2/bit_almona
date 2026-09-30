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
            'customers' => Gate::allows('manage-customers'),
            'reports' => Gate::allows('view-reports'),
            'audit' => Gate::allows('view-audit-logs'),
        ];

        $items = [
            ['label' => 'الرئيسية', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'home'],
            ['label' => 'الطلبات', 'route' => 'admin.orders.index', 'active' => 'admin.orders.*', 'icon' => 'orders', 'gate' => 'orders'],
            ['label' => 'المنتجات', 'route' => 'admin.products.index', 'active' => 'admin.products.*', 'icon' => 'products', 'gate' => 'catalog'],
            ['label' => 'الأقسام', 'route' => 'admin.categories.index', 'active' => 'admin.categories.*', 'icon' => 'categories', 'gate' => 'catalog'],
            ['label' => 'العروض', 'route' => 'admin.offers.index', 'active' => 'admin.offers.*', 'icon' => 'offers', 'gate' => 'catalog'],
            ['label' => 'البنرات', 'route' => 'admin.banners.index', 'active' => 'admin.banners.*', 'icon' => 'banners', 'gate' => 'catalog'],
            ['label' => 'العملاء', 'route' => 'admin.customers.index', 'active' => 'admin.customers.*', 'icon' => 'customers', 'gate' => 'customers'],
            ['label' => 'التقارير', 'route' => 'admin.reports', 'active' => 'admin.reports', 'icon' => 'chart', 'gate' => 'reports'],
            ['label' => 'مناطق التوصيل', 'route' => 'admin.delivery-zones.index', 'active' => 'admin.delivery-zones.*', 'icon' => 'zones', 'gate' => 'delivery'],
            ['label' => 'الإعدادات', 'route' => 'admin.settings', 'active' => 'admin.settings', 'icon' => 'settings', 'gate' => 'settings'],
            ['label' => 'سجل العمليات', 'route' => 'admin.audit-logs', 'active' => 'admin.audit-logs', 'icon' => 'history', 'gate' => 'audit'],
        ];

        return array_values(array_filter($items, fn ($item) => ! isset($item['gate']) || $gates[$item['gate']]));
    }
}
