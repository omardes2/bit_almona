<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Livewire\Admin\Reports\ReportsPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Reports\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status, float $total, string $createdAt, ?string $deliveredAt = null, array $items = []): Order
    {
        $order = Order::factory()->create([
            'status' => $status,
            'total' => $total,
            'created_at' => $createdAt,
            'delivered_at' => $deliveredAt,
        ]);

        foreach ($items as [$name, $qty, $lineTotal, $productId]) {
            $order->items()->create([
                'product_id' => $productId, 'product_name' => $name, 'unit' => 'piece',
                'original_unit_price' => $lineTotal / $qty, 'unit_price' => $lineTotal / $qty, 'quantity' => $qty, 'line_total' => $lineTotal,
            ]);
        }

        return $order;
    }

    public function test_revenue_counts_delivered_orders_only_and_orders_value_excludes_cancelled(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00', 'Asia/Hebron'));

        $this->order('delivered', 100, '2026-09-30 08:00', '2026-09-30 10:00');
        $this->order('delivered', 50, '2026-09-29 08:00', '2026-09-30 09:00'); // created yesterday, delivered today
        $this->order('preparing', 70, '2026-09-30 09:00');
        $this->order('cancelled', 999, '2026-09-30 09:30');

        $report = app(SalesReport::class);
        [$start, $end] = $report->range('today');

        $this->assertSame(['revenue' => 150.0, 'count' => 2, 'average' => 75.0], $report->revenue($start, $end));
        $this->assertSame(['value' => 170.0, 'count' => 2, 'cancelled' => 1], $report->ordersValue($start, $end));
        $this->assertSame(1, $report->statusDistribution($start, $end)['cancelled']);
    }

    public function test_day_boundaries_use_asia_hebron(): void
    {
        // 23:30 Hebron time on the 29th is still "yesterday" for a report run on the 30th.
        $this->travelTo(CarbonImmutable::parse('2026-09-30 00:30', 'Asia/Hebron'));

        $this->order('delivered', 40, '2026-09-29 20:00', '2026-09-29 23:30');
        $this->order('delivered', 60, '2026-09-29 20:00', '2026-09-30 00:10');

        $report = app(SalesReport::class);
        [$start, $end] = $report->range('today');

        $this->assertSame('Asia/Hebron', $start->timezoneName);
        $this->assertSame('2026-09-30 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame(60.0, $report->revenue($start, $end)['revenue']);

        $series = $report->dailyRevenue(7);
        $this->assertCount(7, $series);
        $this->assertSame(40.0, $series['2026-09-29']);
        $this->assertSame(60.0, $series['2026-09-30']);
    }

    public function test_custom_date_range(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00', 'Asia/Hebron'));
        $this->order('delivered', 10, '2026-09-01 10:00', '2026-09-01 12:00');
        $this->order('delivered', 20, '2026-09-10 10:00', '2026-09-10 12:00');
        $this->order('delivered', 30, '2026-09-20 10:00', '2026-09-20 12:00');

        $report = app(SalesReport::class);
        [$start, $end] = $report->range('custom', '2026-09-05', '2026-09-20');

        $this->assertSame(50.0, $report->revenue($start, $end)['revenue']);

        // Reversed dates are swapped, invalid dates fall back safely.
        [$s2, $e2] = $report->range('custom', '2026-09-20', '2026-09-05');
        $this->assertTrue($s2->lt($e2));
        $report->range('custom', 'not-a-date', "'; drop table orders; --");
        $this->assertSame(3, Order::count());
    }

    public function test_top_products_use_delivered_orders_and_name_snapshots(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00', 'Asia/Hebron'));

        $cheese = Product::factory()->create()->id;
        $bread = Product::factory()->create()->id;

        $this->order('delivered', 0, '2026-09-30 08:00', '2026-09-30 09:00', [['جبنة', 3, 30, $cheese], ['زيت (محذوف)', 1, 40, null]]);
        $this->order('delivered', 0, '2026-09-30 08:00', '2026-09-30 10:00', [['جبنة', 2, 20, $cheese]]);
        $this->order('cancelled', 0, '2026-09-30 08:00', null, [['جبنة', 50, 500, $cheese]]);
        $this->order('preparing', 0, '2026-09-30 08:00', null, [['خبز', 99, 99, $bread]]);

        $report = app(SalesReport::class);
        [$start, $end] = $report->range('today');
        $top = $report->topProducts($start, $end);

        $this->assertSame(['جبنة', 'زيت (محذوف)'], $top->pluck('name')->all());
        $this->assertEquals(5, (float) $top[0]->quantity);
        $this->assertEquals(50, (float) $top[0]->sales);
    }

    public function test_reports_page_access(): void
    {
        $this->get('/admin/reports')->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get('/admin/reports')->assertForbidden();
        $this->actingAs(User::factory()->admin(AdminRole::Staff)->create())->get('/admin/reports')->assertForbidden();

        $manager = User::factory()->admin(AdminRole::Manager)->create();
        $this->actingAs($manager)->get('/admin/reports')->assertOk()->assertSee('المبيعات المحققة')->assertSee('المسلّمة');

        foreach (array_keys(SalesReport::PERIODS) as $period) {
            Livewire::actingAs($manager)->test(ReportsPage::class)->set('period', $period)->set('chartDays', 30)->assertOk();
        }
    }
}
