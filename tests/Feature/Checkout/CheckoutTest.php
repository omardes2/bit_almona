<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\SaleUnit;
use App\Enums\SettingType;
use App\Livewire\Auth\Login;
use App\Livewire\Store\Checkout;
use App\Models\Cart;
use App\Models\Offer;
use App\Models\Order;
use App\Models\StoreSetting;
use App\Services\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use CheckoutTestHelpers, RefreshDatabase;

    public function test_guests_are_sent_to_login_and_come_back_to_checkout_with_their_cart(): void
    {
        $product = $this->product();
        $this->addToCart($product, 2);

        $this->get('/checkout')->assertRedirect(route('login'));
        $this->get('/login')->assertSee('لإتمام طلبك');

        $customer = $this->customer();
        Livewire::test(Login::class)
            ->set('phone', $customer->phone)
            ->set('password', 'password1')
            ->call('login')
            ->assertRedirect(url('/checkout'));

        $this->assertSame('2.000', Cart::firstWhere('user_id', $customer->id)->items()->first()->quantity);
    }

    public function test_authenticated_customer_can_open_checkout(): void
    {
        $customer = $this->customer();
        $this->addressFor($customer);
        $this->zone(['name' => 'عين سارة']);
        $this->actingAs($customer);
        $this->addToCart($this->product(['name' => 'جبنة']));

        $this->get('/checkout')->assertOk()->assertSee('إتمام الطلب')->assertSee('جبنة')->assertSee('عين سارة')->assertSee('الدفع عند الاستلام');
    }

    public function test_empty_cart_cannot_checkout(): void
    {
        $this->actingAs($this->customer());

        Livewire::test(Checkout::class)->assertRedirect(route('cart'));
    }

    public function test_a_complete_order_is_created_with_server_side_totals(): void
    {
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $zone = $this->zone(['name' => 'عين سارة', 'delivery_fee' => 12.5]);
        $cheese = $this->product(['name' => 'جبنة', 'sku' => 'CHS-1', 'original_price' => 18, 'sale_price' => 18, 'stock_quantity' => 10]);
        Offer::factory()->for($cheese)->create(['original_price' => 18, 'offer_price' => 10]);
        $kilo = $this->product(['name' => 'بندورة', 'unit' => SaleUnit::Kilogram, 'original_price' => 4, 'sale_price' => 4, 'stock_quantity' => 5, 'min_order_quantity' => 0.5, 'quantity_step' => 0.25]);
        $this->actingAs($customer);
        $this->addToCart($cheese, 2);
        $this->addToCart($kilo, '1.25');

        Livewire::test(Checkout::class)
            ->set('addressId', $address->id)
            ->set('zoneId', $zone->id)
            ->set('notes', 'الرجاء الاتصال قبل الوصول')
            ->assertSee('37.50 ₪') // 2×10 + 1.25×4 + 12.5
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertRedirect(route('order.confirmed', Order::first()));

        $order = Order::first();
        // subtotal at original prices: 2×18 + 5 = 41; discount 16; items 25; + 12.50 delivery
        $this->assertSame('41.00', $order->subtotal);
        $this->assertSame('16.00', $order->discount_total);
        $this->assertSame('12.50', $order->delivery_fee);
        $this->assertSame('37.50', $order->total);
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame(PaymentMethod::CashOnDelivery, $order->payment_method);
        $this->assertMatchesRegularExpression('/^BM-\d{6}-[2-9A-HJKMNP-Z]{5}$/', $order->order_number);
        $this->assertSame('الرجاء الاتصال قبل الوصول', $order->customer_notes);

        // Item snapshots
        $items = $order->items()->orderBy('id')->get();
        $this->assertSame(['جبنة', 'بندورة'], $items->pluck('product_name')->all());
        $this->assertSame('CHS-1', $items[0]->product_sku);
        $this->assertSame('10.00', $items[0]->unit_price);
        $this->assertSame('18.00', $items[0]->original_unit_price);
        $this->assertSame('2.000', $items[0]->quantity);
        $this->assertSame('20.00', $items[0]->line_total);
        $this->assertSame(SaleUnit::Kilogram, $items[1]->unit);
        $this->assertSame('1.250', $items[1]->quantity);
        $this->assertSame('5.00', $items[1]->line_total);

        // Delivery snapshot
        $this->assertSame('مستلم الطلب', $order->recipient_name);
        $this->assertSame('0599111222', $order->recipient_phone);
        $this->assertSame('عين سارة', $order->delivery_zone_name);
        $this->assertSame('الخليل', $order->delivery_city);
        $this->assertStringContainsString('شارع السلام', $order->delivery_address);

        // Stock decreased, cart cleared, payment + history created.
        $this->assertSame('8.000', $cheese->fresh()->stock_quantity);
        $this->assertSame('3.750', $kilo->fresh()->stock_quantity);
        $this->assertSame(0, app(CartService::class)->count());
        $this->assertSame(0, Cart::count());
        $payment = $order->payment;
        $this->assertSame('cash_on_delivery', $payment->provider);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('37.50', $payment->amount);
        $this->assertSame('ILS', $payment->currency);
        $this->assertNull($payment->paid_at);
        $this->assertSame(OrderStatus::New, $order->statusHistory()->first()->to_status);
        $this->assertNotNull($order->stock_deducted_at);
    }

    public function test_address_snapshot_survives_address_edits(): void
    {
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $zone = $this->zone();
        $this->actingAs($customer);
        $this->addToCart($this->product());

        Livewire::test(Checkout::class)->set('addressId', $address->id)->set('zoneId', $zone->id)->call('placeOrder');

        $address->update(['address_line' => 'عنوان جديد تمامًا', 'recipient_name' => 'اسم آخر']);
        $zone->update(['name' => 'اسم منطقة جديد', 'delivery_fee' => 99]);
        $address->delete();

        $order = Order::first()->fresh();
        $this->assertStringContainsString('شارع السلام', $order->delivery_address);
        $this->assertSame('مستلم الطلب', $order->recipient_name);
        $this->assertSame('10.00', $order->delivery_fee);
        $this->assertNotSame('اسم منطقة جديد', $order->delivery_zone_name);
    }

    public function test_checkout_with_a_new_address_saves_it_to_the_account(): void
    {
        $customer = $this->customer();
        $zone = $this->zone();
        $this->actingAs($customer);
        $this->addToCart($this->product());

        Livewire::test(Checkout::class)
            ->assertSet('useNewAddress', true)
            ->set('newAddress.address_line', 'حي الجامعة، شارع 10')
            ->set('newAddress.area', 'الجامعة')
            ->set('zoneId', $zone->id)
            ->call('placeOrder')
            ->assertHasNoErrors();

        $this->assertSame(1, $customer->addresses()->count());
        $this->assertSame($zone->id, $customer->addresses()->first()->delivery_zone_id);
        $this->assertSame(1, Order::count());
    }

    public function test_delivery_fee_comes_from_the_server_and_price_injection_is_ignored(): void
    {
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $zone = $this->zone(['delivery_fee' => 15]);
        $product = $this->product(['original_price' => 20, 'sale_price' => 20]);
        $this->actingAs($customer);
        $this->addToCart($product);
        // A tampered informational price in the cart never reaches the order.
        Cart::first()->items()->update(['unit_price_at_add' => 0.01]);

        $component = Livewire::test(Checkout::class)->set('addressId', $address->id)->set('zoneId', $zone->id);

        // The browser cannot set amounts: there are no such properties and the shown total is locked.
        foreach (['total', 'subtotal', 'deliveryFee', 'delivery_fee', 'price'] as $field) {
            try {
                $component->set($field, '0.01');
            } catch (\Throwable) {
                // expected
            }
        }

        try {
            $component->set('shownTotalCents', 1);
            $this->fail('shownTotalCents must be locked');
        } catch (\Throwable) {
        }

        $component->call('placeOrder')->assertHasNoErrors();

        $order = Order::first();
        $this->assertSame('15.00', $order->delivery_fee);
        $this->assertSame('35.00', $order->total);
        $this->assertSame('20.00', $order->items->first()->unit_price);
    }

    public function test_inactive_zone_cannot_checkout(): void
    {
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $zone = $this->zone(['is_active' => false]);
        $this->actingAs($customer);
        $this->addToCart($this->product());

        Livewire::test(Checkout::class)
            ->assertDontSee($zone->name)
            ->set('addressId', $address->id)
            ->set('zoneId', $zone->id)
            ->call('placeOrder')
            ->assertHasErrors(['zoneId']);

        $this->assertSame(0, Order::count());
    }

    public function test_zone_is_deactivated_between_page_load_and_submit(): void
    {
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $zone = $this->zone();
        $this->actingAs($customer);
        $this->addToCart($this->product());

        $component = Livewire::test(Checkout::class)->set('addressId', $address->id)->set('zoneId', $zone->id);
        $zone->update(['is_active' => false]);
        $component->call('placeOrder');

        $this->assertSame(0, Order::count());
    }

    public function test_the_highest_minimum_order_is_enforced(): void
    {
        StoreSetting::set('min_order_amount', 30, SettingType::Decimal);
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $zone = $this->zone(['min_order_amount' => 50]);
        $cheap = $this->zone(['min_order_amount' => 10]);
        $product = $this->product(['original_price' => 20, 'sale_price' => 20, 'stock_quantity' => 10]);
        $this->actingAs($customer);
        $this->addToCart($product, 2); // 40 ₪

        // Zone minimum 50 > store 30: refused.
        Livewire::test(Checkout::class)
            ->set('addressId', $address->id)->set('zoneId', $zone->id)
            ->assertSee('الحد الأدنى للطلب')
            ->call('placeOrder')
            ->assertSet('error', fn ($error) => str_contains($error, '50.00 ₪'));
        $this->assertSame(0, Order::count());

        // Zone minimum 10 < store 30: 40 passes the store minimum.
        Livewire::test(Checkout::class)->set('addressId', $address->id)->set('zoneId', $cheap->id)->call('placeOrder')->assertSet('error', null);
        $this->assertSame(1, Order::count());
    }

    public function test_unavailable_product_blocks_checkout(): void
    {
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $zone = $this->zone();
        $product = $this->product(['name' => 'زيت']);
        $this->actingAs($customer);
        $this->addToCart($product);

        $component = Livewire::test(Checkout::class)->set('addressId', $address->id)->set('zoneId', $zone->id);
        $product->update(['status' => ProductStatus::Unavailable]);

        $component->call('placeOrder')
            ->assertSet('problems', fn ($problems) => str_contains($problems[0], 'زيت'));

        $this->assertSame(0, Order::count());
        $this->assertSame('10.000', $product->fresh()->stock_quantity);
    }

    public function test_insufficient_stock_blocks_checkout_without_partial_orders(): void
    {
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $zone = $this->zone();
        $ok = $this->product(['name' => 'متوفر', 'stock_quantity' => 10]);
        $short = $this->product(['name' => 'قليل', 'stock_quantity' => 5]);
        $this->actingAs($customer);
        $this->addToCart($ok, 2);
        $this->addToCart($short, 4);

        $short->update(['stock_quantity' => 3]);

        Livewire::test(Checkout::class)
            ->set('addressId', $address->id)->set('zoneId', $zone->id)
            ->call('placeOrder')
            ->assertSet('problems', fn ($problems) => count($problems) === 1 && str_contains($problems[0], 'قليل'));

        $this->assertSame(0, Order::count());
        $this->assertSame('10.000', $ok->fresh()->stock_quantity, 'nothing is deducted on failure');
    }

    public function test_wrong_quantity_blocks_checkout(): void
    {
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $zone = $this->zone();
        $product = $this->product(['min_order_quantity' => 1, 'quantity_step' => 1]);
        $this->actingAs($customer);
        $this->addToCart($product, 3);

        $component = Livewire::test(Checkout::class)->set('addressId', $address->id)->set('zoneId', $zone->id);

        // Quantity rules changed while the checkout page was open (now sold in steps of 2 from 2).
        $product->update(['min_order_quantity' => 2, 'quantity_step' => 2]);
        $component->call('placeOrder')->assertSet('problems', fn ($p) => count($p) === 1);

        // Opening checkout fresh with only invalid lines sends the customer back to the cart.
        Livewire::test(Checkout::class)->assertRedirect(route('cart'));

        $this->assertSame(0, Order::count());
    }

    public function test_price_change_after_the_page_was_shown_is_reported(): void
    {
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $zone = $this->zone();
        $product = $this->product(['original_price' => 20, 'sale_price' => 20]);
        $this->actingAs($customer);
        $this->addToCart($product);

        $component = Livewire::test(Checkout::class)->set('addressId', $address->id)->set('zoneId', $zone->id);
        $product->update(['sale_price' => 15]);

        $component->call('placeOrder')->assertSet('error', fn ($e) => str_contains($e, 'تغيّرت الأسعار'));
        $this->assertSame(0, Order::count());

        // After seeing the new total the customer can confirm.
        $component->call('placeOrder')->assertSet('error', null);
        $this->assertSame('25.00', Order::first()->total);
    }

    public function test_double_submit_creates_only_one_order(): void
    {
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $zone = $this->zone();
        $product = $this->product(['stock_quantity' => 10]);
        $this->actingAs($customer);
        $this->addToCart($product, 2);

        $component = Livewire::test(Checkout::class)->set('addressId', $address->id)->set('zoneId', $zone->id);
        $component->call('placeOrder');
        $first = Order::first();

        // Second click with the same checkout token (cart is already empty by now).
        $component->call('placeOrder')->assertRedirect(route('order.confirmed', $first));

        $this->assertSame(1, Order::count());
        $this->assertSame('8.000', $product->fresh()->stock_quantity);
    }

    public function test_checkout_submit_is_rate_limited(): void
    {
        $customer = $this->customer();
        $this->addressFor($customer);
        $this->actingAs($customer);
        $this->addToCart($this->product());

        $component = Livewire::test(Checkout::class);

        foreach (range(1, 6) as $i) {
            $component->call('placeOrder'); // fails validation (no zone) but counts
        }

        $component->call('placeOrder')->assertSet('error', fn ($e) => str_contains((string) $e, 'محاولات كثيرة'));
    }
}
