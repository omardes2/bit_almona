<?php

namespace Tests\Feature\Store;

use App\Enums\ProductStatus;
use App\Enums\SaleUnit;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\Cart;
use App\Models\User;
use App\Services\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CartMergeTest extends TestCase
{
    use RefreshDatabase, StorefrontTestHelpers;

    private function login(User $user): void
    {
        Livewire::test(Login::class)
            ->set('phone', $user->phone)
            ->set('password', 'password1')
            ->call('login')
            ->assertHasNoErrors();
    }

    public function test_guest_cart_is_merged_into_the_customer_cart_on_login(): void
    {
        $customer = User::factory()->create();
        $shared = $this->product(['stock_quantity' => 10]);
        $guestOnly = $this->product();
        $userOnly = $this->product();

        // Existing customer cart.
        Cart::create(['user_id' => $customer->id])->items()->createMany([
            ['product_id' => $shared->id, 'quantity' => 3],
            ['product_id' => $userOnly->id, 'quantity' => 1],
        ]);

        // Guest shopping before login.
        app(CartService::class)->add($shared->id, 4);
        app(CartService::class)->add($guestOnly->id, 2);

        $this->login($customer);

        $cart = Cart::where('user_id', $customer->id)->first();
        $quantities = $cart->items()->pluck('quantity', 'product_id')->map(fn ($q) => (float) $q)->all();

        $this->assertSame([$shared->id => 7.0, $userOnly->id => 1.0, $guestOnly->id => 2.0], $quantities);
        $this->assertSame(1, Cart::count(), 'the guest cart is removed');
        $this->assertNull(session(CartService::SESSION_KEY));
    }

    public function test_merged_quantity_never_exceeds_stock_and_respects_the_step(): void
    {
        $customer = User::factory()->create();
        $kilo = $this->product(['unit' => SaleUnit::Kilogram, 'min_order_quantity' => 0.5, 'quantity_step' => 0.5, 'stock_quantity' => 2.3]);

        Cart::create(['user_id' => $customer->id])->items()->create(['product_id' => $kilo->id, 'quantity' => 1.5]);
        app(CartService::class)->add($kilo->id, '1.5');

        $this->login($customer);

        // 1.5 + 1.5 = 3, stock 2.3 => largest valid quantity is 2.
        $this->assertSame('2.000', Cart::firstWhere('user_id', $customer->id)->items()->first()->quantity);
    }

    public function test_products_that_became_unbuyable_are_dropped_during_merge(): void
    {
        $customer = User::factory()->create();
        $product = $this->product();
        app(CartService::class)->add($product->id);
        $product->update(['status' => ProductStatus::Unavailable]);

        $this->login($customer);

        $this->assertSame(0, Cart::firstWhere('user_id', $customer->id)->items()->count());
    }

    public function test_guest_cart_is_kept_after_registering(): void
    {
        $product = $this->product();
        app(CartService::class)->add($product->id, 2);

        Livewire::test(Register::class)
            ->set('name', 'زبون جديد')
            ->set('phone', '0599777888')
            ->set('password', 'Secret123')
            ->set('password_confirmation', 'Secret123')
            ->call('register')
            ->assertHasNoErrors();

        $user = User::firstWhere('phone', '0599777888');
        $this->assertSame('2.000', Cart::firstWhere('user_id', $user->id)->items()->first()->quantity);
    }

    public function test_account_link_changes_after_login(): void
    {
        $this->get('/')->assertSee('تسجيل الدخول');

        $this->actingAs(User::factory()->create())->get('/')->assertSee('حسابي')->assertDontSee('تسجيل الدخول');
    }
}
