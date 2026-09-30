<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\AdminRole;
use App\Livewire\Admin\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get(route('admin.login'))->assertOk()->assertSee('دخول لوحة الإدارة');
    }

    public function test_customers_cannot_access_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admins_can_access_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('لوحة التحكم');
    }

    public function test_an_admin_can_log_in_with_phone_and_password(): void
    {
        $admin = User::factory()->admin()->create(['phone' => '0590000000']);

        Livewire::test(Login::class)
            ->set('phone', '0590000000')
            ->set('password', 'password1')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_customers_cannot_log_in_from_the_admin_page(): void
    {
        User::factory()->create(['phone' => '0599123456']);

        Livewire::test(Login::class)
            ->set('phone', '0599123456')
            ->set('password', 'password1')
            ->call('login')
            ->assertHasErrors('phone');

        $this->assertGuest();
    }

    public function test_a_suspended_admin_is_logged_out_on_the_next_request(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();

        $admin->update(['status' => AccountStatus::Suspended]);

        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_admin_routes_can_be_restricted_by_role(): void
    {
        Route::middleware(['web', 'auth', 'admin:super_admin'])->get('/admin/_role-test', fn () => 'ok');

        $this->actingAs(User::factory()->admin(AdminRole::Staff)->create())
            ->get('/admin/_role-test')->assertForbidden();

        $this->actingAs(User::factory()->admin(AdminRole::SuperAdmin)->create())
            ->get('/admin/_role-test')->assertOk();
    }

    public function test_admins_can_be_created_from_the_command_line(): void
    {
        $this->artisan('store:create-admin', ['--name' => 'مدير', '--phone' => '0591234567'])
            ->expectsQuestion('كلمة المرور (8 أحرف على الأقل)', 'Admin12345')
            ->assertSuccessful();

        $admin = User::firstWhere('phone', '0591234567');
        $this->assertTrue($admin->isAdmin());
        $this->assertSame(AdminRole::SuperAdmin, $admin->admin->role);
    }
}
