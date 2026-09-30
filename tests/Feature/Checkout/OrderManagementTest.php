<?php

namespace Tests\Feature\Checkout;

use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Account\Orders;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Orders\OrderIndex;
use App\Livewire\Admin\Orders\OrderShow;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use CheckoutTestHelpers, RefreshDatabase;

    private function placeOrderFor(User $customer, array $productAttributes = [], mixed $quantity = 2): Order
    {
        $this->actingAs($customer);
        $product = $this->product($productAttributes);
        $this->addToCart($product, $quantity);

        return app(PlaceOrder::class)->handle($customer, Str::random(40), $this->addressFor($customer), $this->zone()->id);
    }

    public function test_customer_sees_own_orders_and_details_with_timeline(): void
    {
        $customer = $this->customer();
        $order = $this->placeOrderFor($customer, ['name' => 'عسل']);

        Livewire::actingAs($customer)->test(Orders::class)->assertSee($order->order_number)->assertSee('جديد');

        $this->actingAs($customer)->get(route('account.orders.show', $order))
            ->assertOk()->assertSee('عسل')->assertSee('مراحل الطلب')->assertSee('الدفع عند الاستلام');

        $this->actingAs($customer)->get(route('order.confirmed', $order))
            ->assertOk()->assertSee('تم استلام طلبك بنجاح')->assertSee($order->order_number);
    }

    public function test_customer_cannot_view_another_customers_order_or_confirmation(): void
    {
        $order = $this->placeOrderFor($this->customer());
        $intruder = $this->customer();

        $this->actingAs($intruder)->get(route('account.orders.show', $order))->assertForbidden();
        $this->actingAs($intruder)->get(route('order.confirmed', $order))->assertForbidden();
        Livewire::actingAs($intruder)->test(Orders::class)->assertDontSee($order->order_number);
    }

    public function test_non_admins_cannot_manage_orders(): void
    {
        $order = $this->placeOrderFor($this->customer());
        auth()->logout();

        $this->get('/admin/orders')->assertRedirect(route('admin.login'));
        $this->actingAs($this->customer())->get('/admin/orders')->assertForbidden();
        $this->actingAs($this->customer())->get(route('admin.orders.show', $order))->assertForbidden();

        // Staff handle orders.
        $this->actingAs(User::factory()->admin(AdminRole::Staff)->create())->get(route('admin.orders.show', $order))->assertOk();
    }

    public function test_admin_list_search_and_filters(): void
    {
        $a = $this->placeOrderFor($this->customer());
        $b = $this->placeOrderFor(User::factory()->create(['name' => 'سامي', 'phone' => '0567000111']));
        $admin = User::factory()->admin()->create();
        app(ChangeOrderStatus::class)->handle($b, OrderStatus::Confirmed, $admin);

        $ids = fn ($c) => $c->viewData('orders')->pluck('id')->all();
        $component = Livewire::actingAs($admin)->test(OrderIndex::class);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $ids($component));
        $this->assertSame([$b->id], $ids($component->set('search', 'سامي')));
        $this->assertSame([$b->id], $ids($component->set('search', '+970 567 000 111')));
        $this->assertSame([$a->id], $ids($component->set('search', strtolower($a->order_number))));
        $component->set('search', '');
        $this->assertSame([$b->id], $ids($component->set('status', 'confirmed')));
        $component->set('status', '');
        $this->assertSame([], $ids($component->set('from', now()->addDay()->format('Y-m-d'))));
    }

    public function test_admin_changes_status_through_allowed_transitions_only(): void
    {
        $order = $this->placeOrderFor($this->customer());
        $admin = User::factory()->admin(AdminRole::Staff)->create();
        $component = Livewire::actingAs($admin)->test(OrderShow::class, ['order' => $order]);

        $component->call('changeStatus', 'delivered')->assertDispatched('toast', type: 'error');
        $this->assertSame(OrderStatus::New, $order->fresh()->status);

        foreach (['confirmed', 'preparing', 'out_for_delivery', 'delivered'] as $status) {
            $component->call('changeStatus', $status);
            $this->assertSame($status, $order->fresh()->status->value);
        }

        $history = $order->statusHistory()->get();
        $this->assertSame(['new', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'], $history->pluck('to_status')->map->value->all());
        $this->assertSame($admin->id, $history->last()->changed_by);
        $this->assertSame(4, AuditLog::where('auditable_type', 'order')->where('event', 'status_changed')->count());

        // Delivered is final: no cancelling afterwards.
        $component->set('cancellationReason', 'متأخر')->call('changeStatus', 'cancelled')->assertDispatched('toast', type: 'error');

        // Delivery does not mark cash as collected.
        $this->assertSame(PaymentStatus::Pending, $order->payment->fresh()->status);
    }

    public function test_cod_payment_is_marked_paid_separately(): void
    {
        $order = $this->placeOrderFor($this->customer());
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(OrderShow::class, ['order' => $order])->call('markPaid');

        $payment = $order->payment->fresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertTrue(AuditLog::where('auditable_type', 'payment')->where('event', 'payment_status_changed')->exists());

        Livewire::actingAs($admin)->test(OrderShow::class, ['order' => $order])->call('markPaid')->assertDispatched('toast', type: 'error');
    }

    public function test_cancel_restores_stock_exactly_once(): void
    {
        $order = $this->placeOrderFor($this->customer(), ['stock_quantity' => 10], 3);
        $product = $order->items->first()->product;
        $this->assertSame('7.000', $product->fresh()->stock_quantity);
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(OrderShow::class, ['order' => $order])
            ->call('changeStatus', 'cancelled')->assertHasErrors('cancellationReason')
            ->set('cancellationReason', 'الزبون طلب الإلغاء')
            ->call('changeStatus', 'cancelled')
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('الزبون طلب الإلغاء', $order->cancellation_reason);
        $this->assertNotNull($order->stock_restored_at);
        $this->assertSame('10.000', $product->fresh()->stock_quantity);
        $this->assertSame(PaymentStatus::Cancelled, $order->payment->status);

        // A second cancellation attempt (e.g. a double click or a replayed request) changes nothing.
        try {
            app(ChangeOrderStatus::class)->handle($order, OrderStatus::Cancelled, $admin);
        } catch (\InvalidArgumentException) {
        }
        $this->assertSame('10.000', $product->fresh()->stock_quantity);

        // Even if something bypassed the workflow, the restore guard holds.
        $order->forceFill(['status' => OrderStatus::Preparing])->save();
        app(ChangeOrderStatus::class)->handle($order->fresh(), OrderStatus::Cancelled, $admin);
        $this->assertSame('10.000', $product->fresh()->stock_quantity);
    }

    public function test_dashboard_shows_real_order_numbers(): void
    {
        $order = $this->placeOrderFor($this->customer());
        $admin = User::factory()->admin()->create();
        $second = $this->placeOrderFor($this->customer());
        app(ChangeOrderStatus::class)->handle($second, OrderStatus::Confirmed, $admin);
        app(ChangeOrderStatus::class)->handle($second, OrderStatus::Preparing, $admin);

        Livewire::actingAs($admin)->test(Dashboard::class)
            ->assertViewHas('ordersToday', 2)
            ->assertViewHas('openByStatus', fn ($c) => (int) $c['new'] === 1 && (int) $c['preparing'] === 1)
            ->assertSee(Money::format((float) $order->total + (float) $second->total));
    }
}
