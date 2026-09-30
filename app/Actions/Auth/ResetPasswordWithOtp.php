<?php

namespace App\Actions\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * Final step of the OTP password reset. Only called after OtpService has
 * verified a code for this phone (the caller keeps that proof server-side in
 * the session). Every existing session and "remember me" cookie is revoked.
 */
class ResetPasswordWithOtp
{
    public function __construct(private readonly RevokeUserSessions $revokeSessions) {}

    /** False when no active customer owns the phone (the caller answers generically). */
    public function handle(string $phone, string $password): bool
    {
        $user = User::query()->customers()->active()->where('phone', PhoneNumber::normalize($phone))->first();

        if ($user === null) {
            return false;
        }

        DB::transaction(function () use ($user, $password) {
            $user->forceFill(['password' => $password])->save();
            $this->revokeSessions->handle($user);
            AuditLog::record($user, 'password_reset');
        });

        return true;
    }
}
