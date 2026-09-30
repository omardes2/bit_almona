<?php

namespace Tests\Feature\Auth;

use App\Livewire\Account\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('account'))->assertRedirect(route('login'));
    }

    public function test_customers_can_view_their_account(): void
    {
        $user = User::factory()->create(['name' => 'محمد', 'phone' => '0599123456']);

        $this->actingAs($user)->get(route('account'))
            ->assertOk()
            ->assertSee('محمد')
            ->assertSee('0599123456');
    }

    public function test_admins_are_sent_to_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('account'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_customers_can_update_their_profile_and_password(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Dashboard::class)
            ->set('name', 'اسم جديد')
            ->set('whatsapp', '0561112222')
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertSame('اسم جديد', $user->fresh()->name);
        $this->assertSame('0561112222', $user->customer->fresh()->whatsapp);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->set('current_password', 'wrong')
            ->set('password', 'NewPass123')
            ->set('password_confirmation', 'NewPass123')
            ->call('updatePassword')
            ->assertHasErrors('current_password');

        Livewire::actingAs($user)->test(Dashboard::class)
            ->set('current_password', 'password1')
            ->set('password', 'NewPass123')
            ->set('password_confirmation', 'NewPass123')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));
    }
}
