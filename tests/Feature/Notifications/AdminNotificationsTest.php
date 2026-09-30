<?php

namespace Tests\Feature\Notifications;

use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Events\Orders\OrderPlaced;
use App\Livewire\Admin\Notifications\NotificationBell;
use App\Livewire\Admin\Notifications\NotificationCenter;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Admin\NewOrderPlaced;
use App\Notifications\Admin\OrderWasCancelled;
use App\Notifications\Admin\ProductLowStock;
use App\Notifications\Admin\ProductOutOfStock;
use App\Services\Checkout\CheckoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Checkout\CheckoutTestHelpers;
use Tests\TestCase;

class AdminNotificationsTest extends TestCase
{
    use CheckoutTestHelpers, RefreshDatabase;

    private function placeOrder(array $product = [], mixed $quantity = 1): Order
    {
        $customer = $this->customer();
        $this->actingAs($customer);
        $this->addToCart($this->product($product), $quantity);

        return app(PlaceOrder::class)->handle($customer, Str::random(40), $this->addressFor($customer), $this->zone()->id);
    }

    private function notificationsOf(User $user, string $type)
    {
        return DatabaseNotification::query()->where('notifiable_id', $user->id)->where('type', $type)->get();
    }

    public function test_a_new_order_notifies_admins_after_commit(): void
    {
        $staff = User::factory()->admin(AdminRole::Staff)->create();
        $manager = User::factory()->admin(AdminRole::Manager)->create();

        $order = $this->placeOrder(['name' => 'زيت']);

        foreach ([$staff, $manager] as $admin) {
            $notifications = $this->notificationsOf($admin, NewOrderPlaced::class);
            $this->assertCount(1, $notifications);
            $this->assertStringContainsString($order->order_number, $notifications->first()->data['title']);
            $this->assertSame(route('admin.orders.show', $order, absolute: false), $notifications->first()->data['url']);
        }

        // Customers never get admin notifications.
        $this->assertSame(0, DatabaseNotification::where('notifiable_id', $order->user_id)->count());
    }

    public function test_a_rolled_back_order_does_not_notify_anyone(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->placeOrder();
        DatabaseNotification::query()->delete();

        try {
            DB::transaction(function () use ($order) {
                OrderPlaced::dispatch($order);
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }

        $this->assertCount(0, $this->notificationsOf($admin, NewOrderPlaced::class));

        // And a checkout that fails validation never notifies.
        $customer = $this->customer();
        $this->actingAs($customer);
        $product = $this->product(['stock_quantity' => 1]);
        $this->addToCart($product);
        $product->update(['stock_quantity' => 0]);
        try {
            app(PlaceOrder::class)->handle($customer, Str::random(40), $this->addressFor($customer), $this->zone()->id);
        } catch (CheckoutException) {
        }
        $this->assertCount(0, $this->notificationsOf($admin, NewOrderPlaced::class));
    }

    public function test_cancellation_notifies_other_admins(): void
    {
        $manager = User::factory()->admin(AdminRole::Manager)->create();
        $staff = User::factory()->admin(AdminRole::Staff)->create();
        $order = $this->placeOrder();

        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Cancelled, $staff, 'طلب الزبون');

        $this->assertCount(1, $this->notificationsOf($manager, OrderWasCancelled::class));
        $this->assertCount(0, $this->notificationsOf($staff, OrderWasCancelled::class), 'not the admin who did it');
    }

    public function test_bell_center_mark_read_and_mark_all_read(): void
    {
        $admin = User::factory()->admin()->create();
        $first = $this->placeOrder();
        $this->placeOrder();

        Livewire::actingAs($admin)->test(NotificationBell::class)->assertSee('2')->assertSee($first->order_number);

        $id = $this->notificationsOf($admin, NewOrderPlaced::class)->firstWhere(fn ($n) => str_contains($n->data['title'], $first->order_number))->id;

        Livewire::actingAs($admin)->test(NotificationCenter::class)->call('markRead', $id);
        $this->assertSame(1, $admin->unreadNotifications()->count());

        Livewire::actingAs($admin)->test(NotificationCenter::class)->set('filter', 'unread')
            ->assertViewHas('notifications', fn ($p) => $p->total() === 1);

        Livewire::actingAs($admin)->test(NotificationCenter::class)->call('markAllRead');
        $this->assertSame(0, $admin->unreadNotifications()->count());

        // Opening a notification goes to the order.
        Livewire::actingAs($admin)->test(NotificationBell::class)->call('open', $id)->assertRedirect(route('admin.orders.show', $first, absolute: false));

        // Another admin cannot touch it.
        Livewire::actingAs(User::factory()->admin()->create())->test(NotificationCenter::class)->call('markRead', $id)->assertNotFound();

        $this->actingAs($admin)->get('/admin/notifications')->assertOk();
        $this->actingAs($this->customer())->get('/admin/notifications')->assertForbidden();
    }

    public function test_low_stock_is_notified_once_until_stock_recovers(): void
    {
        $manager = User::factory()->admin(AdminRole::Manager)->create();
        $product = $this->product(['stock_quantity' => 8, 'low_stock_threshold' => 5]);

        // 8 -> 6: still above the threshold.
        $this->placeOrder([], 1); // unrelated product
        $product->update(['stock_quantity' => 6]);
        $this->assertCount(0, $this->notificationsOf($manager, ProductLowStock::class));

        // 6 -> 5 crosses the threshold: one alert.
        $product->update(['stock_quantity' => 5]);
        // 5 -> 4 -> 3: still low, no more alerts.
        $product->update(['stock_quantity' => 4]);
        $product->update(['stock_quantity' => 3]);
        $this->assertCount(1, $this->notificationsOf($manager, ProductLowStock::class));
        $this->assertNotNull($product->fresh()->low_stock_notified_at);

        // Restocked above the threshold => re-armed; dropping again alerts again.
        $product->update(['stock_quantity' => 20]);
        $this->assertNull($product->fresh()->low_stock_notified_at);
        $product->update(['stock_quantity' => 2]);
        $this->assertCount(2, $this->notificationsOf($manager, ProductLowStock::class));
    }

    public function test_checkout_triggers_low_and_out_of_stock_alerts_once(): void
    {
        $manager = User::factory()->admin(AdminRole::Manager)->create();
        $staff = User::factory()->admin(AdminRole::Staff)->create();
        $product = $this->product(['name' => 'حليب', 'stock_quantity' => 6, 'low_stock_threshold' => 5]);

        $buy = function (int $quantity) use ($product) {
            $customer = $this->customer();
            $this->actingAs($customer);
            $this->addToCart($product->fresh(), $quantity);
            app(PlaceOrder::class)->handle($customer, Str::random(40), $this->addressFor($customer), $this->zone()->id);
        };

        $buy(2); // 6 -> 4: low stock
        $buy(1); // 4 -> 3: no repeat
        $this->assertCount(1, $this->notificationsOf($manager, ProductLowStock::class));
        $this->assertCount(0, $this->notificationsOf($staff, ProductLowStock::class), 'stock alerts go to catalog managers');

        $buy(3); // 3 -> 0: out of stock, separate alert
        $out = $this->notificationsOf($manager, ProductOutOfStock::class);
        $this->assertCount(1, $out);
        $this->assertSame('نفد مخزون المنتج', $out->first()->data['title']);
        $this->assertSame('حليب', $out->first()->data['body']);
        $this->assertCount(1, $this->notificationsOf($manager, ProductLowStock::class));

        // Cancelling restores stock (0 -> 3, still low): out-of-stock re-armed, no new low alert.
        app(ChangeOrderStatus::class)->handle(Order::latest('id')->first(), OrderStatus::Cancelled, $manager, 'x');
        $this->assertNull($product->fresh()->out_of_stock_notified_at);
        $this->assertCount(1, $this->notificationsOf($manager, ProductLowStock::class));
    }
}
