<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportsTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<list<string>> */
    private function csv(string $content): array
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'UTF-8 BOM for Excel');
        $lines = array_filter(explode("\n", substr($content, 3)));

        return array_map(fn ($line) => str_getcsv($line, escape: ''), array_values($lines));
    }

    public function test_formula_injection_is_neutralised(): void
    {
        foreach (['=HYPERLINK("x")', '+1', '-2+3', '@SUM(A1)', "\tcmd", "\rcmd"] as $dangerous) {
            $this->assertStringStartsWith("'", Csv::cell($dangerous));
        }

        $this->assertSame('جبنة', Csv::cell('جبنة'));
        $this->assertSame('12.50', Csv::cell('12.50'));
        $this->assertSame('', Csv::cell(null));
    }

    public function test_orders_export(): void
    {
        $manager = User::factory()->admin(AdminRole::Manager)->create();
        $order = Order::factory()->create(['customer_name' => '=cmd|"/c calc"!A1', 'customer_phone' => '0599123456', 'total' => 42.5, 'delivery_fee' => 10]);
        $order->payments()->create(['provider' => 'cash_on_delivery', 'method' => 'cash_on_delivery', 'status' => 'pending', 'amount' => 42.5, 'currency' => 'ILS']);

        $response = $this->actingAs($manager)->get(route('admin.exports', 'orders'));
        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $rows = $this->csv($response->streamedContent());

        $this->assertSame(['رقم الطلب', 'التاريخ', 'العميل', 'الجوال', 'الحالة', 'المبلغ', 'التوصيل', 'طريقة الدفع', 'حالة الدفع'], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame($order->order_number, $rows[1][0]);
        $this->assertSame("'=cmd|\"/c calc\"!A1", $rows[1][2], 'formula escaped');
        $this->assertSame('جديد', $rows[1][4]);
        $this->assertSame('42.50', $rows[1][5]);
        $this->assertSame('الدفع عند الاستلام', $rows[1][7]);
        $this->assertSame('بانتظار الدفع', $rows[1][8]);
        $this->assertTrue(AuditLog::where('event', 'export')->exists());
    }

    public function test_customers_and_products_exports(): void
    {
        $admin = User::factory()->admin(AdminRole::SuperAdmin)->create();
        User::factory()->create(['name' => 'سارة', 'phone' => '0599000111']);
        Product::factory()->for(Category::factory()->create(['name' => 'ألبان']))->create(['name' => '@جبنة', 'sku' => 'CHS-1', 'sale_price' => 18, 'original_price' => 18, 'stock_quantity' => 7]);

        $customers = $this->csv($this->actingAs($admin)->get(route('admin.exports', 'customers'))->streamedContent());
        $this->assertSame('سارة', $customers[1][0]);
        $this->assertSame('0599000111', $customers[1][1]);
        $this->assertSame('0', $customers[1][4]);

        $products = $this->csv($this->actingAs($admin)->get(route('admin.exports', 'products'))->streamedContent());
        $this->assertSame(['CHS-1', "'@جبنة", 'ألبان', '18.00', '7', 'قطعة', 'متوفر'], $products[1]);
    }

    public function test_unauthorized_exports_are_blocked(): void
    {
        $this->get(route('admin.exports', 'orders'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get(route('admin.exports', 'orders'))->assertForbidden();

        $staff = User::factory()->admin(AdminRole::Staff)->create();
        foreach (['orders', 'customers', 'products'] as $type) {
            $this->actingAs($staff)->get(route('admin.exports', $type))->assertForbidden();
        }

        $this->actingAs(User::factory()->admin()->create())->get('/admin/exports/users')->assertNotFound();
    }
}
