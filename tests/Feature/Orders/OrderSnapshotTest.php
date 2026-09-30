<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OrderSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_items_keep_the_name_and_price_from_the_time_of_ordering(): void
    {
        $product = Product::factory()->create(['name' => 'جبنة الخيرات 24 مثلث', 'original_price' => 18, 'sale_price' => 18]);
        Offer::factory()->for($product)->create(['original_price' => 18, 'offer_price' => 10]);

        $order = Order::factory()->create();
        $order->items()->save(OrderItem::snapshotFrom($product, 2));

        $product->update(['name' => 'اسم معدل', 'sale_price' => 25, 'original_price' => 25]);
        $product->offers()->delete();

        $item = $order->fresh()->items->first();

        $this->assertSame('جبنة الخيرات 24 مثلث', $item->product_name);
        $this->assertSame('10.00', $item->unit_price);
        $this->assertSame('18.00', $item->original_unit_price);
        $this->assertSame('20.00', $item->line_total);
    }

    public function test_order_items_survive_product_deletion(): void
    {
        $product = Product::factory()->create();
        $order = Order::factory()->create();
        $order->items()->save(OrderItem::snapshotFrom($product, 1));

        $product->forceDelete();

        $item = $order->fresh()->items->first();
        $this->assertNull($item->product_id);
        $this->assertSame($product->name, $item->product_name);
    }

    public function test_orders_get_a_number_and_follow_the_status_workflow(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create();

        $this->assertStringStartsWith('BM-', $order->order_number);
        $this->assertSame(OrderStatus::New, $order->fresh()->status);

        $order->transitionTo(OrderStatus::Confirmed, $admin);

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->confirmed_at);
        $this->assertSame($admin->id, $order->statusHistory->first()->changed_by);

        $this->expectException(InvalidArgumentException::class);
        $order->transitionTo(OrderStatus::Delivered);
    }
}
