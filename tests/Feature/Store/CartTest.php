<?php

namespace Tests\Feature\Store;

use App\Enums\ProductStatus;
use App\Enums\SaleUnit;
use App\Livewire\Store\CartDrawer;
use App\Livewire\Store\CartPage;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Offer;
use App\Models\User;
use App\Services\Cart\CartException;
use App\Services\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase, StorefrontTestHelpers;

    private function cart(): CartService
    {
        return app(CartService::class);
    }

    private function refused(callable $action, string $messagePart): void
    {
        try {
            $action();
            $this->fail('Expected the cart to refuse the action.');
        } catch (CartException $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    public function test_a_guest_can_add_without_an_account(): void
    {
        $product = $this->product();

        Livewire::test(CartDrawer::class)
            ->dispatch('add-to-cart', productId: $product->id)
            ->assertSet('open', true)
            ->assertDispatched('cart-updated', count: 1)
            ->assertSee($product->name);

        $cart = Cart::first();
        $this->assertNull($cart->user_id);
        $this->assertSame(session(CartService::SESSION_KEY), $cart->session_id);
        $this->assertSame('1.000', $cart->items->first()->quantity);
        $this->assertSame(0, User::count());
    }

    public function test_a_customer_can_add(): void
    {
        $customer = User::factory()->create();
        $product = $this->product();

        Livewire::actingAs($customer)->test(CartDrawer::class)->dispatch('add-to-cart', productId: $product->id);

        $this->assertSame($customer->id, Cart::first()->user_id);
    }

    public function test_adding_the_same_product_increments_one_line(): void
    {
        $product = $this->product();

        $this->cart()->add($product->id);
        $this->cart()->add($product->id, 2);

        $this->assertSame(1, $this->cart()->count());
        $this->assertSame('3', $this->cart()->summary()->lines[0]->quantity);
    }

    public function test_stock_is_enforced(): void
    {
        $product = $this->product(['stock_quantity' => 3]);

        $this->cart()->add($product->id, 2);
        $this->refused(fn () => $this->cart()->add($product->id, 2), 'المتوفر: 3');
        $this->assertSame('2', $this->cart()->summary()->lines[0]->quantity);

        $out = $this->product(['stock_quantity' => 0]);
        $this->refused(fn () => $this->cart()->add($out->id), 'نفدت');
    }

    public function test_minimum_quantity_is_enforced(): void
    {
        $product = $this->product(['unit' => SaleUnit::Kilogram, 'min_order_quantity' => 0.5, 'quantity_step' => 0.25]);

        $this->refused(fn () => $this->cart()->add($product->id, '0.25'), 'أقل كمية');

        // Default quantity is the minimum.
        $this->cart()->add($product->id);
        $this->assertSame('0.5', $this->cart()->summary()->lines[0]->quantity);
    }

    public function test_quantity_step_is_enforced(): void
    {
        $product = $this->product(['unit' => SaleUnit::Kilogram, 'min_order_quantity' => 0.5, 'quantity_step' => 0.25]);

        $this->refused(fn () => $this->cart()->add($product->id, '0.6'), 'بمقدار 0.25');
        $this->refused(fn () => $this->cart()->add($product->id, 'abc'), 'غير صحيحة');
        $this->refused(fn () => $this->cart()->add($product->id, '-1'), 'غير صحيحة');

        $item = $this->cart()->add($product->id, '1.25');
        $this->refused(fn () => $this->cart()->updateQuantity($item->id, '1.3'), 'بمقدار');
        $this->cart()->increment($item->id);
        $this->assertSame('1.5', $this->cart()->summary()->lines[0]->quantity);
    }

    public function test_unavailable_hidden_and_deleted_products_are_rejected(): void
    {
        $unavailable = $this->product(['status' => ProductStatus::Unavailable]);
        $hidden = $this->product(['status' => ProductStatus::Hidden]);
        $deleted = $this->product();
        $deleted->delete();
        $inactiveCategory = $this->product([], Category::factory()->inactive()->create());

        $this->refused(fn () => $this->cart()->add($unavailable->id), 'غير متوفر');
        $this->refused(fn () => $this->cart()->add($hidden->id), 'غير متاح');
        $this->refused(fn () => $this->cart()->add($deleted->id), 'غير متاح');
        $this->refused(fn () => $this->cart()->add($inactiveCategory->id), 'غير متاح');
        $this->refused(fn () => $this->cart()->add(999999), 'غير متاح');

        Livewire::test(CartDrawer::class)
            ->dispatch('add-to-cart', productId: $hidden->id)
            ->assertSet('open', false)
            ->assertDispatched('toast', type: 'error');

        $this->assertSame(0, $this->cart()->count());
    }

    public function test_price_is_calculated_on_the_server(): void
    {
        $product = $this->product(['original_price' => 18, 'sale_price' => 18, 'unit' => SaleUnit::Kilogram, 'min_order_quantity' => 0.5, 'quantity_step' => 0.25]);
        Offer::factory()->for($product)->create(['original_price' => 18, 'offer_price' => 10]);

        $this->cart()->add($product->id, '1.25');
        $summary = $this->cart()->summary();

        $this->assertSame(10.0, $summary->lines[0]->price->finalPrice);
        $this->assertSame('12.50', $summary->lines[0]->lineTotal());
        $this->assertSame('12.50', $summary->subtotal());

        // When the offer ends the cart shows the new real price.
        $this->travel(2)->weeks();
        $this->assertSame('22.50', $this->cart()->summary()->subtotal());
    }

    public function test_the_frontend_cannot_inject_a_price(): void
    {
        $product = $this->product(['original_price' => 20, 'sale_price' => 20]);

        // Extra fields sent with the event are ignored — only id + quantity are read.
        try {
            Livewire::test(CartDrawer::class)->dispatch('add-to-cart', productId: $product->id, quantity: '1', price: '0.01', subtotal: '0.01');
        } catch (\Throwable) {
            // Rejected outright is also fine.
        }

        // Even a tampered informational price column never affects totals.
        $this->cart()->add($product->id);
        $this->cart()->current()->items()->update(['unit_price_at_add' => 0.01]);

        $this->assertSame('40.00', $this->cart()->summary()->subtotal());
    }

    public function test_cart_page_shows_lines_totals_and_no_checkout_yet(): void
    {
        $product = $this->product(['name' => 'حليب', 'original_price' => 6, 'sale_price' => 6]);
        $this->cart()->add($product->id, 2);

        $this->get('/cart')
            ->assertOk()
            ->assertSee('حليب')
            ->assertSee('12.00 ₪')
            ->assertSee('التوصيل يُحسب عند إتمام الطلب')
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_cart_page_quantity_changes_are_validated_on_the_server(): void
    {
        $product = $this->product(['stock_quantity' => 2]);
        $item = $this->cart()->add($product->id);

        $page = Livewire::test(CartPage::class);
        $page->call('increment', $item->id)->assertDispatched('cart-updated', count: 1);
        $page->call('increment', $item->id)->assertDispatched('toast', type: 'error');
        $this->assertSame('2.000', $item->fresh()->quantity);

        $page->call('setQuantity', $item->id, '1');
        $page->call('decrement', $item->id)->assertDispatched('toast', type: 'error');
        $this->assertSame('1.000', $item->fresh()->quantity);

        $page->call('remove', $item->id);
        $this->assertSame(0, $this->cart()->count());
    }

    public function test_another_cart_line_cannot_be_modified(): void
    {
        $product = $this->product();
        $otherCart = Cart::create(['session_id' => 'someone-else']);
        $foreign = $otherCart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $this->cart()->add($product->id);

        $this->refused(fn () => $this->cart()->remove($foreign->id), 'لم يعد في سلتك');
        $this->assertModelExists($foreign);
    }

    public function test_stock_and_price_changes_after_adding_are_reported(): void
    {
        $product = $this->product(['name' => 'زيت', 'stock_quantity' => 10, 'original_price' => 30, 'sale_price' => 30]);
        $gone = $this->product(['name' => 'منتج نفد']);
        $item = $this->cart()->add($product->id, 5);
        $this->cart()->add($gone->id);

        $product->update(['stock_quantity' => 3, 'sale_price' => 25]);
        $gone->update(['status' => ProductStatus::Unavailable]);

        Livewire::test(CartPage::class)
            ->assertSee('تم تعديل كمية «زيت» إلى 3')
            ->assertSee('انخفض سعر «زيت» من 30 ₪ إلى 25 ₪')
            ->assertSee('غير متوفر حاليًا')
            ->assertSee('75.00 ₪'); // 3 × 25, the unavailable line is not counted

        $this->assertSame('3.000', $item->fresh()->quantity);
    }
}
