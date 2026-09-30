<?php

namespace App\Livewire\Account;

use App\Enums\OtpPurpose;
use App\Otp\OtpService;
use App\Otp\OtpThrottledException;
use App\Otp\OtpUnavailableException;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Confirms the customer owns their login phone (OTP to that number).
 */
#[Layout('layouts.app', ['noindex' => true])]
#[Title('توثيق رقم الجوال')]
class VerifyPhone extends Component
{
    #[Locked]
    public bool $codeSent = false;

    public string $code = '';

    public function send(OtpService $otp): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedPhone()) {
            return;
        }

        try {
            $otp->sendCode($user->phone, OtpPurpose::PhoneVerification, request()->ip());
        } catch (OtpThrottledException $e) {
            $this->addError('code', $e->getMessage());

            return;
        } catch (OtpUnavailableException) {
            return;
        }

        $this->codeSent = true;
    }

    public function verify(OtpService $otp)
    {
        $user = Auth::user();
        $this->validate(['code' => ['required', 'string', 'max:10']], [], ['code' => 'رمز التحقق']);

        $key = 'otp-verify:user:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('code', 'محاولات كثيرة. يرجى المحاولة لاحقًا.');

            return null;
        }

        RateLimiter::hit($key, 600);

        if (! $otp->verifyCode($user->phone, OtpPurpose::PhoneVerification, PhoneNumber::westernDigits(trim($this->code)))) {
            $this->addError('code', 'الرمز غير صحيح أو منتهي الصلاحية.');

            return null;
        }

        $user->forceFill(['phone_verified_at' => now()])->save();
        session()->flash('status', 'تم توثيق رقم جوالك.');

        return $this->redirectIntended(route('account'), navigate: true);
    }

    public function render(OtpService $otp)
    {
        return view('livewire.account.verify-phone', [
            'available' => $otp->isAvailable(),
            'user' => Auth::user(),
        ]);
    }
}
