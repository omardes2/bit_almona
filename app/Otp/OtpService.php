<?php

namespace App\Otp;

use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Models\User;
use App\Otp\Contracts\OtpSender;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * One-time codes for phone-based flows (password reset, phone change,
 * phone verification). The architecture is complete; user-facing routes are
 * added once a real WhatsApp/SMS sender is configured (config/otp.php).
 *
 * Security rules:
 *  - codes are random, 6 digits, stored only as a hash;
 *  - they expire (10 min), allow 5 wrong attempts, and work once;
 *  - requesting a new code invalidates the previous one;
 *  - sending is rate limited per phone+purpose and per IP;
 *  - sendCode() answers the same way whether or not the phone is
 *    registered, so it cannot be used to discover accounts.
 */
class OtpService
{
    public function isAvailable(): bool
    {
        $driver = config('otp.driver');

        return filled($driver) && filled(config("otp.drivers.{$driver}"));
    }

    /**
     * Always returns normally for a well-formed phone (no account enumeration).
     *
     * @throws OtpUnavailableException when no sender is configured
     * @throws OtpThrottledException when too many codes were requested
     */
    public function sendCode(string $phone, OtpPurpose $purpose, ?string $ip = null): void
    {
        $phone = PhoneNumber::normalize($phone);
        $sender = $this->sender();

        $this->throttle($phone, $purpose, $ip);

        if (! PhoneNumber::isValid($phone) || ! $this->shouldSend($phone, $purpose)) {
            return; // same outcome as a real send, from the caller's point of view
        }

        $code = $this->generateCode();

        DB::transaction(function () use ($phone, $purpose, $code, $ip) {
            // A new code replaces any previous one for the same purpose.
            OtpCode::query()->where('phone', $phone)->where('purpose', $purpose)->whereNull('used_at')
                ->update(['used_at' => now()]);

            OtpCode::create([
                'phone' => $phone,
                'purpose' => $purpose,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(config('otp.ttl_minutes')),
                'ip_address' => $ip,
            ]);
        });

        $sender->send($phone, $code, $purpose);
    }

    /**
     * True once for a correct, unexpired, unused code; consumes it.
     */
    public function verifyCode(string $phone, OtpPurpose $purpose, string $code): bool
    {
        $phone = PhoneNumber::normalize($phone);

        return DB::transaction(function () use ($phone, $purpose, $code) {
            $otp = OtpCode::query()
                ->where('phone', $phone)
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($otp === null || ! $otp->isUsable()) {
                return false;
            }

            if (! preg_match('/^\d{'.config('otp.length').'}$/', $code) || ! Hash::check($code, $otp->code_hash)) {
                $otp->increment('attempts');

                return false;
            }

            $otp->update(['used_at' => now()]);

            return true;
        });
    }

    private function shouldSend(string $phone, OtpPurpose $purpose): bool
    {
        // A phone change needs a number nobody uses (customer or admin).
        if ($purpose === OtpPurpose::PhoneChange) {
            return ! User::query()->where('phone', $phone)->exists();
        }

        // Reset and verification: only active customer accounts (admins are
        // recovered by a super admin / store:create-admin, never by SMS).
        return User::query()->customers()->active()->where('phone', $phone)->exists();
    }

    private function throttle(string $phone, OtpPurpose $purpose, ?string $ip): void
    {
        $phoneKey = "otp:{$purpose->value}:{$phone}";
        $cooldownKey = "otp-cooldown:{$purpose->value}:{$phone}";
        $ipKey = 'otp-ip:'.($ip ?? 'unknown');

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)
            || RateLimiter::tooManyAttempts($phoneKey, config('otp.max_sends_per_hour'))
            || RateLimiter::tooManyAttempts($ipKey, config('otp.max_sends_per_ip_per_hour'))) {
            throw new OtpThrottledException('طلبت رموزًا كثيرة. يرجى المحاولة لاحقًا.');
        }

        RateLimiter::hit($cooldownKey, config('otp.resend_cooldown_seconds'));
        RateLimiter::hit($phoneKey, 3600);
        RateLimiter::hit($ipKey, 3600);
    }

    private function generateCode(): string
    {
        $length = config('otp.length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    private function sender(): OtpSender
    {
        if (! $this->isAvailable()) {
            throw new OtpUnavailableException('لم يتم إعداد مزود إرسال الرموز بعد.');
        }

        return app(config('otp.drivers.'.config('otp.driver')));
    }
}
