<?php

namespace Tests\Feature\Auth;

use App\Enums\AdminRole;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\FakesOtp;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use FakesOtp, RefreshDatabase;

    public function test_login_page_links_to_password_recovery(): void
    {
        $this->get('/login')->assertSee(route('password.forgot'));
    }

    public function test_service_unavailable_message_when_no_provider_is_configured(): void
    {
        config(['otp.driver' => null]);

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('خدمة استعادة كلمة المرور غير مفعلة حاليًا')
            ->assertDontSee('إرسال الرمز');

        // Even a crafted request cannot trigger a send.
        Livewire::test(ForgotPassword::class)
            ->set('phone', '0599123456')
            ->call('sendCode')
            ->assertSet('step', 'phone');

        $this->assertDatabaseCount('otp_codes', 0);
    }

    public function test_the_same_answer_is_given_for_registered_and_unknown_phones(): void
    {
        $sender = $this->fakeOtp();
        User::factory()->create(['phone' => '0599123456']);

        $known = Livewire::test(ForgotPassword::class)->set('phone', '0599123456')->call('sendCode');
        session()->forget(ForgotPassword::SESSION_KEY);
        $unknown = Livewire::test(ForgotPassword::class)->set('phone', '0599000777')->call('sendCode');

        foreach ([$known, $unknown] as $component) {
            $component->assertHasNoErrors()->assertSet('step', 'code')->assertSee(ForgotPassword::SENT_MESSAGE);
        }

        $this->assertSame(1, $sender->sentTo('0599123456'));
        $this->assertSame(0, $sender->sentTo('0599000777'), 'no code is ever sent to an unknown number');
    }

    public function test_admin_accounts_cannot_be_recovered_by_otp(): void
    {
        $sender = $this->fakeOtp();
        User::factory()->admin(AdminRole::SuperAdmin)->create(['phone' => '0599222333']);

        Livewire::test(ForgotPassword::class)->set('phone', '0599222333')->call('sendCode')->assertSet('step', 'code');

        $this->assertSame(0, $sender->sentTo('0599222333'));
    }

    public function test_sending_is_rate_limited(): void
    {
        $this->fakeOtp();
        User::factory()->create(['phone' => '0599123456']);

        $component = Livewire::test(ForgotPassword::class)->set('phone', '0599123456')->call('sendCode')->assertSet('step', 'code');

        $component->call('resend')->assertHasErrors('code');
        $this->assertStringContainsString('يرجى المحاولة لاحقًا', $component->errors()->first('code'));
    }

    public function test_wrong_codes_are_rejected_and_attempts_are_limited(): void
    {
        $sender = $this->fakeOtp();
        User::factory()->create(['phone' => '0599123456']);

        $component = Livewire::test(ForgotPassword::class)->set('phone', '0599123456')->call('sendCode');
        $good = $sender->lastCodeFor('0599123456');
        $bad = $good === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $component->set('code', $bad)->call('verify')->assertHasErrors('code')->assertSet('step', 'code');
        }

        // Five wrong guesses burn the code: even the right one no longer works.
        $component->set('code', $good)->call('verify')->assertHasErrors('code')->assertSet('step', 'code');
    }

    public function test_full_reset_flow_revokes_old_sessions(): void
    {
        config(['session.driver' => 'database']);
        $sender = $this->fakeOtp();
        $user = User::factory()->create(['phone' => '0599123456', 'remember_token' => 'old-remember-token']);
        DB::table('sessions')->insert([
            ['id' => 'other-device', 'user_id' => $user->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => now()->getTimestamp()],
        ]);

        $component = Livewire::test(ForgotPassword::class)->set('phone', '٠٥٩٩١٢٣٤٥٦')->call('sendCode');
        $code = $sender->lastCodeFor('0599123456');
        $this->assertNotNull($code);

        $component->set('code', $code)->call('verify')->assertHasNoErrors()->assertSet('step', 'password')
            ->set('password', 'short')->set('password_confirmation', 'short')->call('resetPassword')->assertHasErrors('password')
            ->set('password', 'NewPass123')->set('password_confirmation', 'NewPass123')->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('NewPass123', $user->password));
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
        $this->assertTrue(AuditLog::where('event', 'password_reset')->where('auditable_id', $user->id)->exists());
        $this->assertNull(session(ForgotPassword::SESSION_KEY));

        Livewire::test(Login::class)->set('phone', '0599123456')->set('password', 'NewPass123')->call('login')->assertHasNoErrors();
    }

    public function test_the_password_step_cannot_be_reached_without_a_verified_code(): void
    {
        $this->fakeOtp();
        $user = User::factory()->create(['phone' => '0599123456']);
        $before = $user->password;

        Livewire::test(ForgotPassword::class)
            ->set('phone', '0599123456')->call('sendCode')
            ->set('password', 'Hacked1234')->set('password_confirmation', 'Hacked1234')
            ->call('resetPassword')
            ->assertSet('step', 'phone');

        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_the_step_cannot_be_changed_from_the_browser(): void
    {
        $this->fakeOtp();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(ForgotPassword::class)->set('step', 'password');
    }

    public function test_the_verified_state_expires(): void
    {
        $sender = $this->fakeOtp();
        $user = User::factory()->create(['phone' => '0599123456']);
        $before = $user->password;

        $component = Livewire::test(ForgotPassword::class)->set('phone', '0599123456')->call('sendCode');
        $component->set('code', $sender->lastCodeFor('0599123456'))->call('verify')->assertSet('step', 'password');

        $this->travel(11)->minutes();

        $component->set('password', 'NewPass123')->set('password_confirmation', 'NewPass123')->call('resetPassword')->assertSet('step', 'phone');
        $this->assertSame($before, $user->fresh()->password);
    }
}
