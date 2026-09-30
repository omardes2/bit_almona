<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Definitions (shown on the reports page too):
 *  - Revenue (المبيعات المحققة): DELIVERED orders, by delivered_at.
 *  - Orders value (قيمة الطلبات): every NON-cancelled order, by created_at
 *    (includes new/in-progress orders that are not revenue yet).
 *  - Top products: delivered orders only, from order_items snapshots
 *    (works for deleted products too).
 * All day boundaries use the app timezone (Asia/Hebron); timestamps are
 * stored in that timezone.
 */
class SalesReport
{
    public const PERIODS = [
        'today' => 'اليوم',
        '7d' => 'آخر 7 أيام',
        '30d' => 'آخر 30 يوم',
        'month' => 'هذا الشهر',
        'custom' => 'نطاق مخصص',
    ];

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(string $period, ?string $from = null, ?string $to = null): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        return match ($period) {
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            '30d' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'month' => [$now->startOfMonth(), $now->endOfDay()],
            'custom' => $this->customRange($from, $to, $now),
            default => [$now->startOfDay(), $now->endOfDay()],
        };
    }

    /** @return array{revenue: float, count: int, average: float} */
    public function revenue(CarbonInterface $start, CarbonInterface $end): array
    {
        $row = Order::query()
            ->where('status', OrderStatus::Delivered)
            ->whereBetween('delivered_at', [$start, $end])
            ->selectRaw('count(*) as c, coalesce(sum(total), 0) as s')
            ->toBase()->first();

        $count = (int) $row->c;

        return ['revenue' => round((float) $row->s, 2), 'count' => $count, 'average' => $count ? round((float) $row->s / $count, 2) : 0.0];
    }

    /** @return array{value: float, count: int, cancelled: int} */
    public function ordersValue(CarbonInterface $start, CarbonInterface $end): array
    {
        $row = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('sum(case when status != ? then 1 else 0 end) as c', [OrderStatus::Cancelled->value])
            ->selectRaw('coalesce(sum(case when status != ? then total else 0 end), 0) as s', [OrderStatus::Cancelled->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as cancelled', [OrderStatus::Cancelled->value])
            ->toBase()->first();

        return ['value' => round((float) $row->s, 2), 'count' => (int) $row->c, 'cancelled' => (int) $row->cancelled];
    }

    /**
     * Delivered revenue per day for the last $days days (missing days = 0).
     *
     * @return array<string, float> Y-m-d => amount
     */
    public function dailyRevenue(int $days): array
    {
        $end = CarbonImmutable::now(config('app.timezone'))->endOfDay();
        $start = $end->subDays($days - 1)->startOfDay();

        $rows = Order::query()
            ->where('status', OrderStatus::Delivered)
            ->whereBetween('delivered_at', [$start, $end])
            ->selectRaw('date(delivered_at) as day, sum(total) as s')
            ->groupBy('day')
            ->pluck('s', 'day');

        $series = [];

        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $series[$day->toDateString()] = round((float) ($rows[$day->toDateString()] ?? 0), 2);
        }

        return $series;
    }

    /**
     * @return Collection<int, object{product_id: ?int, name: string, quantity: float, sales: float}>
     */
    public function topProducts(CarbonInterface $start, CarbonInterface $end, int $limit = 10): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::Delivered)
            ->whereBetween('orders.delivered_at', [$start, $end])
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->selectRaw('order_items.product_id, order_items.product_name as name, sum(order_items.quantity) as quantity, sum(order_items.line_total) as sales')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->toBase()
            ->get();
    }

    /** @return array<string, int> status => count, for orders created in the range */
    public function statusDistribution(CarbonInterface $start, CarbonInterface $end): array
    {
        $counts = Order::query()->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return collect(OrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)])->all();
    }

    /** Products at or under their alert threshold but not yet at zero. */
    public function lowStock(int $limit = 50): Collection
    {
        return Product::query()->where('status', '!=', ProductStatus::Hidden)
            ->lowStock()->where('stock_quantity', '>', 0)
            ->orderBy('stock_quantity')->limit($limit)
            ->get(['id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold', 'unit', 'main_image']);
    }

    public function outOfStock(int $limit = 50): Collection
    {
        return Product::query()->where('status', '!=', ProductStatus::Hidden)
            ->where('stock_quantity', '<=', 0)
            ->orderBy('name')->limit($limit)
            ->get(['id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold', 'unit', 'main_image']);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function customRange(?string $from, ?string $to, CarbonImmutable $now): array
    {
        $parse = fn (?string $value) => $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
            ? rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d', $value, config('app.timezone')), null, false)
            : null;

        $start = ($parse($from) ?? $now->subDays(29))->startOfDay();
        $end = ($parse($to) ?? $now)->endOfDay();

        return $start <= $end ? [$start, $end] : [$end->startOfDay(), $start->endOfDay()];
    }
}
