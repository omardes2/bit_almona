<?php

namespace App\Livewire\Account;

use App\Actions\Account\ChangePhone as ChangePhoneAction;
use App\Enums\OtpPurpose;
use App\Otp\OtpService;
use App\Otp\OtpThrottledException;
use App\Otp\OtpUnavailableException;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Change the login phone: current password + new number → OTP sent to the
 * NEW number → verify → update (unique, re-checked under lock) → every other
 * session is signed out. Disabled while no OTP sender is configured.
 */
#[Layout('layouts.app', ['noindex' => true])]
#[Title('تغيير رقم الجوال')]
class ChangePhone extends Component
{
    public const SESSION_KEY = 'phone_change';

    #[Locked]
    public string $step = 'details';

    public string $current_password = '';

    public string $newPhone = '';

    public string $code = '';

    public function mount(): void
    {
        if ($this->state()) {
            $this->step = 'code';
        }
    }

    public function sendCode(OtpService $otp): void
    {
        $user = Auth::user();
        $this->newPhone = PhoneNumber::normalize($this->newPhone);

        $this->validate([
            'current_password' => ['required', 'string'],
            'newPhone' => ['required', 'regex:'.PhoneNumber::PATTERN],
        ], [
            'newPhone.regex' => 'رقم الجوال يجب أن يكون بالصيغة 05XXXXXXXX.',
        ], ['current_password' => 'كلمة المرور الحالية', 'newPhone' => 'الرقم الجديد']);

        if ($this->newPhone === $user->phone) {
            $this->addError('newPhone', 'هذا هو رقمك الحالي.');

            return;
        }

        $key = 'phone-change-password:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('current_password', 'محاولات كثيرة. يرجى المحاولة لاحقًا.');

            return;
        }

        if (! Hash::check($this->current_password, $user->password)) {
            RateLimiter::hit($key, 600);
            $this->addError('current_password', 'كلمة المرور غير صحيحة.');

            return;
        }

        RateLimiter::clear($key);

        try {
            // Sends nothing if the number is already used — the answer stays the same.
            $otp->sendCode($this->newPhone, OtpPurpose::PhoneChange, request()->ip());
        } catch (OtpThrottledException $e) {
            $this->addError('newPhone', $e->getMessage());

            return;
        } catch (OtpUnavailableException) {
            return;
        }

        session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'phone' => $this->newPhone,
            'expires_at' => now()->addMinutes(config('otp.ttl_minutes'))->getTimestamp(),
        ]);

        $this->reset('current_password', 'code');
        $this->step = 'code';
    }

    public function verify(OtpService $otp, ChangePhoneAction $changePhone)
    {
        $state = $this->state();

        if (! $state) {
            $this->restart();

            return null;
        }

        $this->validate(['code' => ['required', 'string', 'max:10']], [], ['code' => 'رمز التحقق']);

        $key = 'otp-verify:user:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('code', 'محاولات كثيرة. يرجى المحاولة لاحقًا.');

            return null;
        }

        RateLimiter::hit($key, 600);

        if (! $otp->verifyCode($state['phone'], OtpPurpose::PhoneChange, PhoneNumber::westernDigits(trim($this->code)))) {
            $this->addError('code', 'الرمز غير صحيح أو منتهي الصلاحية.');

            return null;
        }

        try {
            $changePhone->handle(Auth::user(), $state['phone'], session()->getId());
        } catch (ValidationException) {
            session()->forget(self::SESSION_KEY);
            $this->addError('code', 'لا يمكن استخدام هذا الرقم. جرّب رقمًا آخر.');

            return null;
        }

        session()->forget(self::SESSION_KEY);
        session()->regenerate();
        session()->flash('status', 'تم تغيير رقم الجوال. استخدم الرقم الجديد لتسجيل الدخول.');

        return $this->redirectRoute('account', navigate: true);
    }

    public function restart(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->reset('code', 'current_password');
        $this->step = 'details';
    }

    /**
     * @return array{user_id: int, phone: string, expires_at: int}|null
     */
    private function state(): ?array
    {
        $state = session(self::SESSION_KEY);

        if (! is_array($state) || ($state['user_id'] ?? null) !== Auth::id() || ($state['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return $state;
    }

    public function render(OtpService $otp)
    {
        return view('livewire.account.change-phone', [
            'available' => $otp->isAvailable(),
            'user' => Auth::user(),
            'maskedPhone' => ($state = $this->state()) ? PhoneNumber::mask($state['phone']) : null,
        ]);
    }
}
