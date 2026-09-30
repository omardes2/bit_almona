<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\ResetPasswordWithOtp;
use App\Enums\OtpPurpose;
use App\Otp\OtpService;
use App\Otp\OtpThrottledException;
use App\Otp\OtpUnavailableException;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Password reset by OTP: phone → code → new password.
 *
 * - Works only once an OtpSender is configured (config/otp.php); otherwise a
 *   "service not enabled" message is shown.
 * - The same answer is given whether or not the phone has an account.
 * - Which phone was verified is kept server-side in the session (never
 *   trusted from the browser), and expires after a few minutes.
 * - Resetting revokes every existing session / remember-me cookie.
 */
#[Layout('layouts.app', ['noindex' => true])]
#[Title('استعادة كلمة المرور')]
class ForgotPassword extends Component
{
    public const SESSION_KEY = 'password_reset';

    public const SENT_MESSAGE = 'إذا كان الرقم مسجّلًا لدينا فستصلك رسالة برمز التحقق خلال لحظات.';

    public const WRONG_CODE = 'الرمز غير صحيح أو منتهي الصلاحية.';

    /** Minutes the verified state stays valid before the new password must be set. */
    private const VERIFIED_TTL = 10;

    #[Locked]
    public string $step = 'phone';

    public string $phone = '';

    public string $code = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $state = $this->state();

        if ($state && $state['verified']) {
            $this->step = 'password';
        } elseif ($state) {
            $this->step = 'code';
        }
    }

    public function sendCode(OtpService $otp): void
    {
        $this->phone = PhoneNumber::normalize($this->phone);

        $this->validate(
            ['phone' => ['required', 'regex:'.PhoneNumber::PATTERN]],
            ['phone.regex' => 'رقم الجوال يجب أن يكون بالصيغة 05XXXXXXXX.'],
            ['phone' => 'رقم الجوال'],
        );

        if (! $this->send($otp, $this->phone)) {
            return;
        }

        session()->put(self::SESSION_KEY, ['phone' => $this->phone, 'verified' => false, 'expires_at' => now()->addMinutes(config('otp.ttl_minutes'))->getTimestamp()]);
        $this->reset('code');
        $this->step = 'code';
    }

    public function resend(OtpService $otp): void
    {
        $state = $this->state();

        if (! $state) {
            $this->restart();

            return;
        }

        if ($this->send($otp, $state['phone'])) {
            session()->flash('otp-status', 'أُرسل رمز جديد.');
        }
    }

    public function verify(OtpService $otp): void
    {
        $state = $this->state();

        if (! $state) {
            $this->restart();

            return;
        }

        $this->validate(['code' => ['required', 'string', 'max:10']], [], ['code' => 'رمز التحقق']);

        $key = 'otp-verify:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('code', 'محاولات كثيرة. يرجى المحاولة لاحقًا.');

            return;
        }

        RateLimiter::hit($key, 600);

        if (! $otp->verifyCode($state['phone'], OtpPurpose::PasswordReset, PhoneNumber::westernDigits(trim($this->code)))) {
            $this->addError('code', self::WRONG_CODE);

            return;
        }

        session()->put(self::SESSION_KEY, ['phone' => $state['phone'], 'verified' => true, 'expires_at' => now()->addMinutes(self::VERIFIED_TTL)->getTimestamp()]);
        $this->reset('code');
        $this->step = 'password';
    }

    public function resetPassword(ResetPasswordWithOtp $reset)
    {
        $state = $this->state();

        if (! $state || ! $state['verified']) {
            $this->restart();

            return null;
        }

        $this->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [], ['password' => 'كلمة المرور']);

        $reset->handle($state['phone'], $this->password);

        session()->forget(self::SESSION_KEY);
        session()->flash('status', 'تم تغيير كلمة المرور. سجّل الدخول بكلمة المرور الجديدة.');

        return $this->redirectRoute('login', navigate: true);
    }

    public function restart(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->reset('code', 'password', 'password_confirmation');
        $this->step = 'phone';
    }

    private function send(OtpService $otp, string $phone): bool
    {
        try {
            $otp->sendCode($phone, OtpPurpose::PasswordReset, request()->ip());
        } catch (OtpThrottledException $e) {
            $this->addError($this->step === 'phone' ? 'phone' : 'code', $e->getMessage());

            return false;
        } catch (OtpUnavailableException) {
            return false; // the view already shows "service not enabled"
        }

        return true;
    }

    /**
     * @return array{phone: string, verified: bool, expires_at: int}|null
     */
    private function state(): ?array
    {
        $state = session(self::SESSION_KEY);

        if (! is_array($state) || ($state['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return $state;
    }

    public function render(OtpService $otp)
    {
        return view('livewire.auth.forgot-password', [
            'available' => $otp->isAvailable(),
            'maskedPhone' => ($state = $this->state()) ? PhoneNumber::mask($state['phone']) : null,
        ]);
    }
}
