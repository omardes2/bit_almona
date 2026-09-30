<?php

namespace Tests\Feature\Performance;

use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Livewire\Admin\Orders\OrderIndex;
use App\Livewire\Admin\Reports\ReportsPage;
use App\Livewire\Store\CartPage;
use App\Livewire\Store\Checkout;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Checkout\CheckoutTestHelpers;
use Tests\TestCase;

/**
 * Query counts must not grow with the number of products / lines / orders
 * (no N+1). Lazy loading also throws outside production (Model::shouldBeStrict).
 */
class QueryCountTest extends TestCase
{
    use CheckoutTestHelpers, RefreshDatabase;

    private function queries(callable $callback): int
    {
        $callback(); // warm caches (settings, category tree, admin role)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function products(Category $category, int $count): void
    {
        Product::factory()->count($count)->for($category)->create([
            'name' => 'منتج اختبار', 'status' => 'available', 'stock_quantity' => 50,
            'min_order_quantity' => 1, 'quantity_step' => 1, 'is_featured' => true,
        ])->each(fn (Product $p, int $i) => $i % 2 ? Offer::factory()->for($p)->create(['original_price' => $p->original_price, 'offer_price' => max(1, $p->original_price - 1)]) : null);
    }

    /**
     * @param  callable(): void  $grow
     * @return array{0: int, 1: int}
     */
    private function assertFlat(callable $measure, callable $grow, int $max): array
    {
        $few = $this->queries($measure);
        $grow();
        $many = $this->queries($measure);

        $this->assertSame($few, $many, "queries grew from {$few} to {$many}");
        $this->assertLessThanOrEqual($max, $many);

        return [$few, $many];
    }

    public function test_storefront_pages(): void
    {
        // Enough products that every home section is already populated, so the
        // comparison only measures growth (an empty section skips its eager load).
        $category = Category::factory()->create(['name' => 'قسم']);
        $this->products($category, 20);
        $product = Product::first();

        $pages = [
            'home' => ['/', 18],
            'category' => [$category->url(), 18],
            'search' => ['/search?q='.urlencode('منتج'), 18],
            'product' => [$product->url(), 20],
            'offers' => ['/offers', 18],
        ];

        foreach ($pages as $name => [$url, $max]) {
            $few = $this->queries(fn () => $this->get($url)->assertOk());
            $counts[$name] = $few;
        }

        $this->products($category, 20);

        foreach ($pages as $name => [$url, $max]) {
            $many = $this->queries(fn () => $this->get($url)->assertOk());
            $this->assertSame($counts[$name], $many, "{$name}: {$counts[$name]} => {$many}");
            $this->assertLessThanOrEqual($max, $many, $name);
        }
    }

    public function test_cart_and_checkout(): void
    {
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $zone = $this->zone();
        $this->actingAs($customer);
        $category = Category::factory()->create();

        $add = function (int $count) use ($category) {
            $this->products($category, $count);
            Product::query()->whereDoesntHave('offers', fn ($q) => $q->whereRaw('1 = 0'))->get()
                ->each(fn (Product $p) => rescue(fn () => app(CartService::class)->add($p->id, 1), report: false));
        };

        $add(2);

        $this->assertFlat(fn () => Livewire::test(CartPage::class), fn () => $add(10), 25);
        $this->assertFlat(
            fn () => Livewire::test(Checkout::class)->set('addressId', $address->id)->set('zoneId', $zone->id),
            fn () => $add(6),
            40,
        );
    }

    public function test_admin_orders_and_reports(): void
    {
        $admin = User::factory()->admin(AdminRole::SuperAdmin)->create();
        $zone = $this->zone();
        $category = Category::factory()->create();
        $this->products($category, 4);

        $placeOrders = function (int $count) use ($zone) {
            for ($i = 0; $i < $count; $i++) {
                $customer = $this->customer();
                Auth::login($customer);
                Product::query()->limit(2)->get()->each(fn (Product $p) => app(CartService::class)->add($p->id, 1));
                $order = app(PlaceOrder::class)->handle($customer, Str::random(20), $this->addressFor($customer), $zone->id);
                Auth::logout();

                if ($i % 2 === 0) {
                    foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::OutForDelivery, OrderStatus::Delivered] as $status) {
                        app(ChangeOrderStatus::class)->handle($order->fresh(), $status, User::query()->admins()->first());
                    }
                }
            }
        };

        $placeOrders(2);

        $this->assertFlat(fn () => Livewire::actingAs($admin)->test(OrderIndex::class), fn () => $placeOrders(8), 15);
        $this->assertFlat(fn () => Livewire::actingAs($admin)->test(ReportsPage::class), fn () => $placeOrders(4), 30);
    }
}
