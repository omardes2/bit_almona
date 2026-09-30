<?php

namespace Tests\Feature\Account;

use App\Livewire\Account\ChangePhone;
use App\Livewire\Account\VerifyPhone;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Checkout\CheckoutTestHelpers;
use Tests\Support\FakesOtp;
use Tests\TestCase;

class PhoneChangeAndVerificationTest extends TestCase
{
    use CheckoutTestHelpers, FakesOtp, RefreshDatabase;

    public function test_phone_change_is_disabled_without_a_provider(): void
    {
        config(['otp.driver' => null]);
        $this->actingAs($user = User::factory()->create());

        $this->get('/account/phone')
            ->assertOk()
            ->assertSee('تغيير رقم الجوال غير متاح حاليًا')
            ->assertDontSee('إرسال رمز إلى الرقم الجديد');

        Livewire::test(ChangePhone::class)
            ->set('current_password', 'password1')->set('newPhone', '0598777666')
            ->call('sendCode')
            ->assertSet('step', 'details');

        $this->assertSame($user->phone, $user->fresh()->phone);
    }

    public function test_the_current_password_is_required(): void
    {
        $sender = $this->fakeOtp();
        $this->actingAs(User::factory()->create());

        Livewire::test(ChangePhone::class)
            ->set('current_password', 'wrong-password')->set('newPhone', '0598777666')
            ->call('sendCode')
            ->assertHasErrors('current_password')
            ->assertSet('step', 'details');

        $this->assertSame([], $sender->sent);
    }

    public function test_a_number_used_by_another_account_never_receives_a_code(): void
    {
        $sender = $this->fakeOtp();
        User::factory()->create(['phone' => '0598777666']);
        $this->actingAs(User::factory()->create());

        // Same answer as for a free number (no enumeration), but nothing is sent.
        Livewire::test(ChangePhone::class)
            ->set('current_password', 'password1')->set('newPhone', '0598777666')
            ->call('sendCode')
            ->assertHasNoErrors()
            ->assertSet('step', 'code')
            ->set('code', '123456')->call('verify')
            ->assertHasErrors('code');

        $this->assertSame(0, $sender->sentTo('0598777666'));
    }

    public function test_full_phone_change_flow(): void
    {
        config(['session.driver' => 'database']);
        $sender = $this->fakeOtp();
        $user = User::factory()->create(['phone' => '0599111000']);
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => now()->getTimestamp()]);
        $this->actingAs($user);

        $component = Livewire::test(ChangePhone::class)
            ->set('current_password', 'password1')->set('newPhone', '0598777666')
            ->call('sendCode')
            ->assertSet('step', 'code');

        $this->assertSame(1, $sender->sentTo('0598777666'), 'the code goes to the NEW number');
        $this->assertSame(0, $sender->sentTo('0599111000'));

        $component->set('code', $sender->lastCodeFor('0598777666'))->call('verify')
            ->assertHasNoErrors()
            ->assertRedirect(route('account'));

        $user->refresh();
        $this->assertSame('0598777666', $user->phone);
        $this->assertTrue($user->hasVerifiedPhone());
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
        $this->assertTrue(AuditLog::where('event', 'phone_changed')->where('auditable_id', $user->id)->exists());
    }

    public function test_the_new_number_is_rechecked_when_confirming(): void
    {
        $sender = $this->fakeOtp();
        $user = User::factory()->create(['phone' => '0599111000']);
        $this->actingAs($user);

        $component = Livewire::test(ChangePhone::class)
            ->set('current_password', 'password1')->set('newPhone', '0598777666')
            ->call('sendCode');

        // Someone registers the number between the send and the confirmation.
        User::factory()->create(['phone' => '0598777666']);

        $component->set('code', $sender->lastCodeFor('0598777666'))->call('verify')->assertHasErrors('code');
        $this->assertSame('0599111000', $user->fresh()->phone);
    }

    public function test_verify_phone_marks_the_phone_as_verified(): void
    {
        $sender = $this->fakeOtp();
        $user = User::factory()->create(['phone' => '0599111000']);
        $this->actingAs($user);

        Livewire::test(VerifyPhone::class)
            ->call('send')
            ->assertSet('codeSent', true)
            ->set('code', $sender->lastCodeFor('0599111000'))
            ->call('verify')
            ->assertHasNoErrors();

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_verify_phone_page_without_provider(): void
    {
        config(['otp.driver' => null]);
        $this->actingAs(User::factory()->create());

        $this->get('/account/verify-phone')->assertOk()->assertSee('خدمة توثيق رقم الجوال غير مفعلة حاليًا');
    }

    public function test_phone_verification_is_enforced_only_with_flag_and_provider(): void
    {
        $customer = $this->customer();
        $this->addressFor($customer);
        $this->zone();
        $this->actingAs($customer);
        $this->addToCart($this->product());

        // Flag on, no provider: not enforced (nobody could verify).
        config(['store.require_phone_verification' => true, 'otp.driver' => null]);
        $this->get('/checkout')->assertOk();

        // Provider on, flag off: not enforced.
        $this->fakeOtp();
        config(['store.require_phone_verification' => false]);
        $this->get('/checkout')->assertOk();

        // Both: unverified customers go verify first, then come back.
        config(['store.require_phone_verification' => true]);
        $this->get('/checkout')->assertRedirect(route('account.verify-phone'));

        $customer->forceFill(['phone_verified_at' => now()])->save();
        $this->get('/checkout')->assertOk();
    }
}
