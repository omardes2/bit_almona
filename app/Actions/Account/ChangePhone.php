<?php

namespace App\Actions\Account;

use App\Actions\Auth\RevokeUserSessions;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces a customer's login phone after the NEW number was verified by OTP.
 * Uniqueness is re-checked under a lock (and enforced by the unique index);
 * the new number counts as verified, and every other session is signed out.
 */
class ChangePhone
{
    public function __construct(private readonly RevokeUserSessions $revokeSessions) {}

    public function handle(User $user, string $newPhone, ?string $keepSessionId = null): void
    {
        $newPhone = PhoneNumber::normalize($newPhone);

        DB::transaction(function () use ($user, $newPhone, $keepSessionId) {
            $taken = User::query()->where('phone', $newPhone)->whereKeyNot($user->id)->lockForUpdate()->exists();

            if ($taken) {
                throw ValidationException::withMessages(['newPhone' => 'لا يمكن استخدام هذا الرقم.']);
            }

            $old = $user->phone;
            $user->forceFill(['phone' => $newPhone, 'phone_verified_at' => now()])->save();

            $this->revokeSessions->handle($user, $keepSessionId);
            AuditLog::record($user, 'phone_changed', ['phone' => $old], ['phone' => $newPhone]);
        });
    }
}
