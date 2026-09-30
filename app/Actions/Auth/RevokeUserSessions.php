<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signs a user out everywhere (optionally keeping the current session):
 * rotates the "remember me" token and deletes their database sessions.
 * Production uses SESSION_DRIVER=database (docs/PRODUCTION_CHECKLIST.md).
 */
class RevokeUserSessions
{
    public function handle(User $user, ?string $keepSessionId = null): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->when($keepSessionId, fn ($query) => $query->where('id', '!=', $keepSessionId))
                ->delete();
        }
    }
}
