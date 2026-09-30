<?php

namespace Tests\Feature\System;

use App\Actions\Checkout\PlaceOrder;
use App\Enums\AccountStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Checkout\CheckoutTestHelpers;
use Tests\TestCase;

class DemoDataPurgeTest extends TestCase
{
    use CheckoutTestHelpers, RefreshDatabase;

    public function test_purge_never_touches_real_data(): void
    {
        $this->seed(DemoDataSeeder::class);
        $realCustomer = $this->customer();
        $realProduct = $this->product(['name' => 'زيت زيتون', 'sku' => 'OIL-1']);
        $realCategory = $realProduct->category;
        Offer::factory()->for($realProduct)->create(['original_price' => 20, 'offer_price' => 15]);

        $this->artisan('store:purge-demo', ['--force' => true])->assertSuccessful();

        $this->assertModelExists($realCustomer);
        $this->assertModelExists($realProduct);
        $this->assertModelExists($realCategory);
        $this->assertSame(1, Offer::count());
        $this->assertFalse(Product::withTrashed()->where('sku', DemoDataSeeder::PRODUCT_SKU)->exists());
        $this->assertFalse(Category::where('slug', DemoDataSeeder::CATEGORY_SLUG)->exists());
        $this->assertNull(User::firstWhere('phone', DemoDataSeeder::CUSTOMER_PHONE));
    }

    public function test_demo_rows_used_by_orders_are_kept_for_history(): void
    {
        $this->seed(DemoDataSeeder::class);
        $demoCustomer = User::firstWhere('phone', DemoDataSeeder::CUSTOMER_PHONE);
        $demoProduct = Product::firstWhere('sku', DemoDataSeeder::PRODUCT_SKU);
        $zone = $this->zone();

        $this->actingAs($demoCustomer);
        $this->addToCart($demoProduct, 1);
        $order = app(PlaceOrder::class)->handle($demoCustomer, 'demo-token', $this->addressFor($demoCustomer), $zone->id);

        $this->artisan('store:purge-demo', ['--force' => true])->assertSuccessful();

        // The order and its lines survive intact.
        $this->assertModelExists($order);
        $this->assertSame($demoProduct->id, $order->items()->first()->product_id);
        $this->assertSame($demoCustomer->id, Order::find($order->id)->user_id);

        // The product is hidden + soft deleted, its offer stopped; the customer is suspended.
        $product = Product::withTrashed()->find($demoProduct->id);
        $this->assertTrue($product->trashed());
        $this->assertSame(ProductStatus::Hidden, $product->status);
        $this->assertFalse(Offer::where('product_id', $product->id)->where('is_active', true)->exists());
        $this->assertSame(AccountStatus::Suspended, $demoCustomer->fresh()->status);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->artisan('store:purge-demo', ['--dry-run' => true])->assertSuccessful();

        $this->assertTrue(Product::where('sku', DemoDataSeeder::PRODUCT_SKU)->exists());
        $this->assertNotNull(User::firstWhere('phone', DemoDataSeeder::CUSTOMER_PHONE));
    }
}
