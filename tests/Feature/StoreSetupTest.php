<?php

namespace Tests\Feature;

use App\Enums\SettingType;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_is_configured_for_arabic_and_hebron(): void
    {
        $this->assertSame('ar', app()->getLocale());
        $this->assertSame('Asia/Hebron', config('app.timezone'));
        $this->assertSame('Asia/Hebron', now()->timezoneName);
    }

    public function test_the_home_page_renders_rtl(): void
    {
        $this->seed();

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('بيت المونة')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_seeding_creates_settings_admin_and_demo_data(): void
    {
        config([
            'store.seed_admin.phone' => '0590000000',
            'store.seed_admin.password' => 'Admin12345',
            'store.seed_demo_data' => true,
        ]);

        $this->seed();

        $this->assertSame('بيت المونة', StoreSetting::get('store_name'));
        $this->assertSame('ILS', StoreSetting::get('currency_code'));
        $this->assertSame('10.00 ₪', Money::format(10));

        $this->assertTrue(User::firstWhere('phone', '0590000000')->isAdmin());

        $product = Product::firstWhere('sku', DemoDataSeeder::PRODUCT_SKU);
        $this->assertSame('جبنة الخيرات 24 مثلث', $product->name);
        $this->assertSame('ألبان وأجبان', $product->category->name);
        $this->assertSame('18.00', $product->original_price);
        $this->assertSame('10.00', $product->activeOffer->offer_price);
    }

    public function test_demo_data_is_easy_to_remove(): void
    {
        config(['store.seed_demo_data' => true]);
        $this->seed();

        $this->artisan('store:purge-demo', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Product::withTrashed()->count());
        $this->assertSame(0, Offer::count());
        $this->assertSame(0, Category::count());
        $this->assertNull(User::firstWhere('phone', DemoDataSeeder::CUSTOMER_PHONE));
        $this->assertSame('بيت المونة', StoreSetting::get('store_name'));
    }

    public function test_settings_are_typed_and_cache_is_refreshed_on_save(): void
    {
        StoreSetting::set('min_order_amount', 25.5, SettingType::Decimal);
        $this->assertSame(25.5, StoreSetting::get('min_order_amount'));

        StoreSetting::set('min_order_amount', 30);
        $this->assertSame(30.0, StoreSetting::get('min_order_amount'));
    }
}
