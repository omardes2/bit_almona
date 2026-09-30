<?php

namespace Tests\Feature\Auth;

use App\Enums\UserType;
use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_renders_in_arabic_rtl(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('إنشاء حساب جديد');
    }

    public function test_a_customer_can_register_with_phone_and_password_without_email(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'أحمد التميمي')
            ->set('phone', '+970 599 123 456')
            ->set('whatsappSameAsPhone', false)
            ->set('whatsapp', '0569876543')
            ->set('password', 'Secret123')
            ->set('password_confirmation', 'Secret123')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('account'));

        $user = User::where('phone', '0599123456')->firstOrFail();

        $this->assertSame(UserType::Customer, $user->type);
        $this->assertNull($user->email);
        $this->assertSame('0569876543', $user->customer->whatsapp);
        $this->assertNotSame('Secret123', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('Secret123', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_whatsapp_defaults_to_the_phone_number(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'سارة')
            ->set('phone', '0599111222')
            ->set('password', 'Secret123')
            ->set('password_confirmation', 'Secret123')
            ->call('register')
            ->assertHasNoErrors();

        $this->assertSame('0599111222', User::firstWhere('phone', '0599111222')->customer->whatsapp);
    }

    public function test_registration_is_validated(): void
    {
        User::factory()->create(['phone' => '0599123456']);

        Livewire::test(Register::class)
            ->set('name', '')
            ->set('phone', '0599123456')
            ->set('password', 'short')
            ->set('password_confirmation', 'different')
            ->call('register')
            ->assertHasErrors(['name' => 'required', 'phone' => 'unique', 'password']);

        Livewire::test(Register::class)
            ->set('phone', '12345')
            ->call('register')
            ->assertHasErrors(['phone' => 'regex']);

        $this->assertGuest();
    }
}
