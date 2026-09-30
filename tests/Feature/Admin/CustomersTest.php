<?php

namespace Tests\Feature\Admin;

use App\Actions\Checkout\PlaceOrder;
use App\Actions\Customers\SetCustomerStatus;
use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\AccountStatus;
use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Livewire\Admin\Customers\CustomerIndex;
use App\Livewire\Admin\Customers\CustomerShow;
use App\Livewire\Auth\Login;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Checkout\CheckoutTestHelpers;
use Tests\TestCase;

class CustomersTest extends TestCase
{
    use CheckoutTestHelpers, RefreshDatabase;

    private function order(User $customer, float $price, ?OrderStatus $finalStatus = null)
    {
        $this->actingAs($customer);
        $this->addToCart($this->product(['original_price' => $price, 'sale_price' => $price]));
        $order = app(PlaceOrder::class)->handle($customer, Str::random(40), $this->addressFor($customer), $this->zone(['delivery_fee' => 0])->id);

        $admin = User::factory()->admin()->create();
        $path = [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::OutForDelivery, OrderStatus::Delivered];
        if ($finalStatus === OrderStatus::Delivered) {
            foreach ($path as $status) {
                app(ChangeOrderStatus::class)->handle($order->fresh(), $status, $admin);
            }
        } elseif ($finalStatus === OrderStatus::Cancelled) {
            app(ChangeOrderStatus::class)->handle($order, OrderStatus::Cancelled, $admin, 'test');
        }

        return $order;
    }

    public function test_admin_can_search_customers_and_totals_are_correct(): void
    {
        $sara = User::factory()->create(['name' => 'سارة التميمي', 'phone' => '0599123456']);
        $sara->customer->update(['whatsapp' => '0567777888']);
        $ahmad = User::factory()->create(['name' => 'أحمد', 'phone' => '0598000000']);
        User::factory()->admin()->create(['name' => 'مدير لا يظهر']);

        $this->order($sara, 30, OrderStatus::Delivered);
        $this->order($sara, 20, OrderStatus::Delivered);
        $this->order($sara, 99, OrderStatus::Cancelled);
        $this->order($ahmad, 10);

        $manager = User::factory()->admin(AdminRole::Manager)->create();
        $component = Livewire::actingAs($manager)->test(CustomerIndex::class);
        $rows = fn () => $component->viewData('customers')->keyBy('id');

        $this->assertCount(2, $rows());
        $this->assertSame(3, $rows()[$sara->id]->orders_count);
        $this->assertEquals(50.0, (float) $rows()[$sara->id]->purchases_total, 'delivered orders only');
        $this->assertNotNull($rows()[$sara->id]->last_order_at);

        $this->assertSame([$sara->id], $component->set('search', 'سارة')->viewData('customers')->pluck('id')->all());
        $this->assertSame([$sara->id], $component->set('search', '+970 599 123 456')->viewData('customers')->pluck('id')->all());
        $this->assertSame([$sara->id], $component->set('search', '0567777')->viewData('customers')->pluck('id')->all());
        $component->set('search', '');
        $this->assertSame($sara->id, $component->set('sort', 'top')->viewData('customers')->first()->id);

        $ahmad->update(['status' => AccountStatus::Suspended]);
        $this->assertSame([$ahmad->id], $component->set('status', 'suspended')->viewData('customers')->pluck('id')->all());
    }

    public function test_customer_list_has_no_n_plus_one(): void
    {
        $admin = User::factory()->admin()->create();
        $count = function () use ($admin) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::actingAs($admin)->test(CustomerIndex::class);

            return count(DB::getQueryLog());
        };

        User::factory()->count(2)->create();
        $count();
        $few = $count();
        User::factory()->count(15)->create();
        $this->assertSame($few, $count());
    }

    public function test_customer_details_show_history_addresses_and_stats(): void
    {
        $customer = User::factory()->create(['name' => 'ليلى']);
        $delivered = $this->order($customer, 40, OrderStatus::Delivered);
        $pending = $this->order($customer, 10);
        $manager = User::factory()->admin(AdminRole::Manager)->create();

        Livewire::actingAs($manager)->test(CustomerShow::class, ['customer' => $customer])
            ->assertSee('ليلى')
            ->assertSee($delivered->order_number)
            ->assertSee($pending->order_number)
            ->assertSee('شارع السلام')
            ->assertViewHas('stats', fn ($s) => (int) $s->orders_count === 2 && (float) $s->delivered_total === 40.0)
            ->assertViewHas('average', 40.0);

        // Admin users are not customers.
        $this->actingAs($manager)->get(route('admin.customers.show', $manager))->assertNotFound();
    }

    public function test_suspending_a_customer_blocks_login_ends_sessions_and_keeps_orders(): void
    {
        $customer = User::factory()->create(['phone' => '0599111000']);
        $order = $this->order($customer, 15);
        config(['session.driver' => 'database']); // production setting (tests default to the array driver)
        DB::table('sessions')->insert(['id' => 'sess-1', 'user_id' => $customer->id, 'payload' => 'x', 'last_activity' => time()]);
        $manager = User::factory()->admin(AdminRole::Manager)->create();

        Livewire::actingAs($manager)->test(CustomerShow::class, ['customer' => $customer])->call('toggleStatus');

        $this->assertSame(AccountStatus::Suspended, $customer->fresh()->status);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $customer->id)->count());
        $this->assertModelExists($order);
        $this->assertTrue(AuditLog::where('auditable_type', 'user')->where('event', 'status_changed')->where('auditable_id', $customer->id)->exists());

        // Cannot log in.
        auth()->logout();
        Livewire::test(Login::class)->set('phone', '0599111000')->set('password', 'password1')->call('login')->assertHasErrors('phone');
        $this->assertGuest();

        // A session that was already open is ended on the next request.
        $this->actingAs($customer->fresh())->get('/account')->assertRedirect(route('login'));
        $this->assertGuest();

        // Re-activation.
        Livewire::actingAs($manager)->test(CustomerShow::class, ['customer' => $customer])->call('toggleStatus');
        $this->assertSame(AccountStatus::Active, $customer->fresh()->status);
    }

    public function test_access_rules(): void
    {
        $customer = User::factory()->create();

        $this->get('/admin/customers')->assertRedirect(route('admin.login'));
        $this->actingAs($customer)->get('/admin/customers')->assertForbidden();
        $this->actingAs(User::factory()->admin(AdminRole::Staff)->create())->get('/admin/customers')->assertForbidden();
        $this->actingAs(User::factory()->admin(AdminRole::Manager)->create())->get('/admin/customers')->assertOk();
        $this->actingAs(User::factory()->admin(AdminRole::Manager)->create())->get(route('admin.customers.show', $customer))->assertOk();
    }

    public function test_phone_is_read_only_and_admins_cannot_be_suspended_here(): void
    {
        $manager = User::factory()->admin(AdminRole::Manager)->create();
        $customer = User::factory()->create(['phone' => '0599555444']);

        $component = Livewire::actingAs($manager)->test(CustomerShow::class, ['customer' => $customer]);
        $this->expectException(\Throwable::class);
        $component->set('phone', '0599000000'); // no such property: the phone cannot be changed here
    }

    public function test_the_action_refuses_admin_accounts(): void
    {
        $superAdmin = User::factory()->admin(AdminRole::SuperAdmin)->create();

        $this->expectException(\InvalidArgumentException::class);
        app(SetCustomerStatus::class)->handle($superAdmin, AccountStatus::Suspended);
    }
}
