<?php

namespace Tests\Feature\Checkout;

use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Store\Checkout;
use App\Models\Order;
use App\Models\User;
use App\Payments\PaymentException;
use App\Payments\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\Support\FakeOnlinePaymentProvider;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use CheckoutTestHelpers, RefreshDatabase;

    /** @return array{0: User, 1: int, 2: int} customer, address id, zone id */
    private function readyCheckout(): array
    {
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $zone = $this->zone();
        $this->actingAs($customer);
        $this->addToCart($this->product());

        return [$customer, $address->id, $zone->id];
    }

    public function test_cash_on_delivery_is_shown_with_its_timing(): void
    {
        $this->readyCheckout();

        $this->get('/checkout')
            ->assertOk()
            ->assertSee('الدفع عند الاستلام')
            ->assertSee('ادفع نقدًا عند استلام طلبك.')
            ->assertSee('value="cash_on_delivery"', false);

        $options = app(PaymentManager::class)->checkoutOptions();
        $this->assertCount(1, $options);
        $this->assertFalse($options[0]->online);
        $this->assertSame('cash', $options[0]->icon);
    }

    public function test_a_disabled_method_is_hidden_and_refused(): void
    {
        [, $addressId, $zoneId] = $this->readyCheckout();
        config(['payments.methods.cash_on_delivery.enabled' => false]);

        $this->get('/checkout')
            ->assertOk()
            ->assertDontSee('value="cash_on_delivery"', false)
            ->assertSee('لا توجد طريقة دفع متاحة حاليًا');

        Livewire::test(Checkout::class)
            ->set('addressId', $addressId)->set('zoneId', $zoneId)
            ->set('paymentMethod', 'cash_on_delivery')
            ->call('placeOrder')
            ->assertHasErrors('paymentMethod');

        $this->assertSame(0, Order::count());
    }

    public function test_a_method_without_a_real_provider_class_is_never_offered(): void
    {
        config(['payments.methods.cash_on_delivery.provider' => 'App\\Payments\\Providers\\DoesNotExist']);

        $this->assertSame([], app(PaymentManager::class)->enabledMethods());
    }

    public function test_the_browser_cannot_invent_a_payment_method(): void
    {
        [, $addressId, $zoneId] = $this->readyCheckout();

        foreach (['credit_card', 'paypal', 'free', '', 'cash_on_delivery ', 'CASH_ON_DELIVERY'] as $invented) {
            Livewire::test(Checkout::class)
                ->set('addressId', $addressId)->set('zoneId', $zoneId)
                ->set('paymentMethod', $invented)
                ->call('placeOrder')
                ->assertHasErrors('paymentMethod');
        }

        $this->assertSame(0, Order::count());
    }

    public function test_an_online_provider_redirects_to_its_https_page_after_the_order_is_saved(): void
    {
        [, $addressId, $zoneId] = $this->readyCheckout();
        config(['payments.methods.cash_on_delivery.provider' => FakeOnlinePaymentProvider::class]);

        $this->get('/checkout')->assertSee('دفع فوري عبر الإنترنت');

        Livewire::test(Checkout::class)
            ->set('addressId', $addressId)->set('zoneId', $zoneId)
            ->call('placeOrder')
            ->assertRedirect(FakeOnlinePaymentProvider::$url);

        // The order exists and its payment is still pending: only a (future) signed webhook may mark it paid.
        $this->assertSame(PaymentStatus::Pending, Order::sole()->payment->status);
    }

    public function test_a_non_https_redirect_from_a_provider_is_ignored(): void
    {
        [, $addressId, $zoneId] = $this->readyCheckout();
        config(['payments.methods.cash_on_delivery.provider' => FakeOnlinePaymentProvider::class]);
        FakeOnlinePaymentProvider::$url = 'javascript:alert(1)';

        try {
            $component = Livewire::test(Checkout::class)
                ->set('addressId', $addressId)->set('zoneId', $zoneId)
                ->call('placeOrder');

            $component->assertRedirect(route('order.confirmed', Order::sole()));
        } finally {
            FakeOnlinePaymentProvider::$url = 'https://pay.example.test/checkout/123';
        }
    }

    public function test_orders_can_still_be_cancelled_after_their_method_is_disabled(): void
    {
        [, $addressId, $zoneId] = $this->readyCheckout();
        Livewire::test(Checkout::class)->set('addressId', $addressId)->set('zoneId', $zoneId)->call('placeOrder');
        $order = Order::sole();

        config(['payments.methods.cash_on_delivery.enabled' => false]);

        $this->assertThrows(fn () => app(PaymentManager::class)->provider($order->payment_method), PaymentException::class);

        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Cancelled, User::factory()->admin()->create(), 'اختبار');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Cancelled, $order->fresh()->payment->status);
    }

    public function test_unexpected_errors_show_a_generic_message_and_log_safe_context_only(): void
    {
        [$customer, $addressId, $zoneId] = $this->readyCheckout();

        $this->app->instance(PlaceOrder::class, Mockery::mock(PlaceOrder::class, function ($mock) {
            $mock->shouldReceive('handle')->andThrow(new RuntimeException('Deadlock while saving 0599111222 شارع السلام'));
        }));

        Log::spy();

        Livewire::test(Checkout::class)
            ->set('addressId', $addressId)->set('zoneId', $zoneId)
            ->set('notes', 'ملاحظة خاصة')
            ->call('placeOrder')
            ->assertSet('error', 'حدث خطأ غير متوقع، حاول مرة أخرى')
            ->assertDontSee('Deadlock')
            ->assertNoRedirect();

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) use ($customer, $zoneId) {
            $json = json_encode($context, JSON_UNESCAPED_UNICODE);

            return $message === 'checkout.unexpected_error'
                && $context['user_id'] === $customer->id
                && $context['zone_id'] === $zoneId
                && $context['error'] === RuntimeException::class
                && strlen($context['checkout_ref']) === 12
                && ! str_contains($json, 'ملاحظة خاصة')
                && ! array_key_exists('address', $context)
                && ! array_key_exists('notes', $context);
        });

        $this->assertSame(0, Order::count());
    }
}
