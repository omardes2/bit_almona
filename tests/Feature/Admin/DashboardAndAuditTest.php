<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Products\ProductForm;
use App\Livewire\Admin\Products\ProductIndex;
use App\Models\AuditLog;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_real_statistics(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->count(3)->create();
        Product::factory()->status(ProductStatus::Unavailable)->create(['stock_quantity' => 1, 'low_stock_threshold' => 5]);
        Offer::factory()->create();
        Offer::factory()->expired()->create();
        Banner::factory()->create();
        Order::factory()->create(['total' => 55.5]);
        Order::factory()->create(['total' => 100, 'status' => OrderStatus::Cancelled]);

        Livewire::actingAs($admin)->test(Dashboard::class)
            ->assertViewHas('products', fn ($p) => (int) $p->total === 6 && (int) $p->unavailable === 1 && (int) $p->low_stock === 1)
            ->assertViewHas('runningOffers', 1)
            ->assertViewHas('runningBanners', 1)
            ->assertViewHas('ordersToday', 1)
            ->assertSee('55.50 ₪');
    }

    public function test_admin_header_uses_the_store_name_setting_with_a_fallback(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertSee('بيت المونة');

        StoreSetting::set('store_name', 'بيت المونة - الخليل');
        $this->actingAs($admin)->get('/admin')->assertSee('بيت المونة - الخليل');
    }

    public function test_admin_changes_are_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin);

        Livewire::test(ProductForm::class)
            ->set('name', 'حليب')
            ->set('category_id', $category->id)
            ->set('original_price', '6')
            ->set('sale_price', '5')
            ->call('save');

        $product = Product::firstWhere('name', 'حليب');

        Livewire::test(ProductForm::class, ['product' => $product])->set('sale_price', '4.5')->call('save');
        Livewire::test(ProductIndex::class)->call('delete', $product->id);

        $logs = AuditLog::where('auditable_type', 'product')->where('auditable_id', $product->id)->orderBy('id')->get();

        $this->assertSame(['created', 'updated', 'deleted'], $logs->pluck('event')->all());
        $this->assertSame($admin->id, $logs[0]->user_id);
        $this->assertSame('5.00', $logs[1]->old_values['sale_price']);
        $this->assertSame('4.50', $logs[1]->new_values['sale_price']);
        $this->assertArrayNotHasKey('search_text', $logs[0]->new_values);

        // Passwords never end up in audit logs (users are not audited at all).
        $this->assertSame(0, AuditLog::where('auditable_type', 'like', '%user%')->count());
    }
}
