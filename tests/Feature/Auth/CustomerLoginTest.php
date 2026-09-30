<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('تسجيل الدخول');
    }

    public function test_a_customer_can_log_in_with_phone_and_password(): void
    {
        $user = User::factory()->create(['phone' => '0599123456']);

        Livewire::test(Login::class)
            ->set('phone', '+970599123456')
            ->set('password', 'password1')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('account'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['phone' => '0599123456']);

        Livewire::test(Login::class)
            ->set('phone', '0599123456')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('phone');

        $this->assertGuest();
    }

    public function test_admins_cannot_log_in_from_the_customer_page(): void
    {
        User::factory()->admin()->create(['phone' => '0599123456']);

        Livewire::test(Login::class)
            ->set('phone', '0599123456')
            ->set('password', 'password1')
            ->call('login')
            ->assertHasErrors('phone');

        $this->assertGuest();
    }

    public function test_suspended_customers_cannot_log_in(): void
    {
        User::factory()->suspended()->create(['phone' => '0599123456']);

        Livewire::test(Login::class)
            ->set('phone', '0599123456')
            ->set('password', 'password1')
            ->call('login')
            ->assertHasErrors(['phone' => __('auth.suspended')]);

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['phone' => '0599123456']);

        foreach (range(1, config('store.login.max_attempts')) as $attempt) {
            Livewire::test(Login::class)
                ->set('phone', '0599123456')
                ->set('password', 'wrong-password')
                ->call('login');
        }

        // Even the correct password is refused while locked out.
        Livewire::test(Login::class)
            ->set('phone', '0599123456')
            ->set('password', 'password1')
            ->call('login')
            ->assertHasErrors('phone');

        $this->assertGuest();
    }

    public function test_a_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_logout_requires_a_csrf_token(): void
    {
        $user = User::factory()->create();

        // The testing kernel skips CSRF; make sure the route really uses the web group.
        $this->assertContains('web', app('router')->getRoutes()->getByName('logout')->gatherMiddleware());
        $this->actingAs($user)->get('/logout')->assertMethodNotAllowed();
    }
}
