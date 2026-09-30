<?php

namespace Tests\Feature\Checkout;

use App\Actions\Checkout\PlaceOrder;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Overselling: several customers race for the last units. The stock rows are
 * locked (SELECT ... FOR UPDATE) and re-validated inside the transaction, so
 * later checkouts see the reduced stock and are refused. (True parallelism is
 * verified against MySQL separately; here the checkouts run back to back.)
 */
class ConcurrencyTest extends TestCase
{
    use CheckoutTestHelpers, RefreshDatabase;

    public function test_the_last_unit_can_only_be_sold_once(): void
    {
        $product = $this->product(['stock_quantity' => 1]);
        $zone = $this->zone();

        $buyers = collect(range(1, 3))->map(function () use ($product) {
            $customer = $this->customer();
            $this->actingAs($customer);
            $this->addToCart($product, 1); // everyone could add it while stock was 1

            return $customer;
        });

        $succeeded = 0;

        foreach ($buyers as $customer) {
            $this->actingAs($customer);

            try {
                app(PlaceOrder::class)->handle($customer, Str::random(40), $this->addressFor($customer), $zone->id);
                $succeeded++;
            } catch (CheckoutException $e) {
                $this->assertStringContainsString('تغيّرت', $e->getMessage());
            }
        }

        $this->assertSame(1, $succeeded);
        $this->assertSame(1, Order::count());
        $this->assertSame('0.000', $product->fresh()->stock_quantity);
    }

    public function test_stock_never_goes_negative(): void
    {
        $product = $this->product(['stock_quantity' => 5]);
        $zone = $this->zone();

        $first = $this->customer();
        $this->actingAs($first);
        $this->addToCart($product, 4);

        $second = $this->customer();
        $this->actingAs($second);
        $this->addToCart($product, 3);

        $this->actingAs($first);
        app(PlaceOrder::class)->handle($first, Str::random(40), $this->addressFor($first), $zone->id);

        $this->actingAs($second);
        $this->expectException(CheckoutException::class);

        try {
            app(PlaceOrder::class)->handle($second, Str::random(40), $this->addressFor($second), $zone->id);
        } finally {
            $this->assertSame('1.000', $product->fresh()->stock_quantity);
            $this->assertGreaterThanOrEqual(0, (float) $product->fresh()->stock_quantity);
        }
    }

    /**
     * Regression: under MySQL REPEATABLE READ a plain read taken before the
     * row lock returns stale stock. Checkout must validate the rows it locked.
     */
    public function test_checkout_validates_against_the_locked_rows_not_a_fresh_read(): void
    {
        $product = $this->product(['stock_quantity' => 3]);
        $customer = $this->customer();
        $this->actingAs($customer);
        $this->addToCart($product, 2);

        // Simulate what the lock returns in production: the row as updated by a
        // concurrent checkout (1 left), while a stale snapshot would still say 3.
        $locked = Product::whereKey($product->id)->get()->keyBy('id');
        $locked[$product->id]->stock_quantity = '1.000';

        $cart = Cart::firstWhere('user_id', $customer->id);
        $line = app(CartService::class)->summary($cart, $locked)->lines[0];

        $this->assertFalse($line->purchasable);
        $this->assertNotNull($line->issue);
    }
}
