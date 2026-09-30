<?php

namespace App\Actions\Auth;

use App\Enums\UserType;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Phone + password login shared by the customer and admin login pages.
 * Each page only accepts its own user type, and failures are rate limited
 * per phone number + IP address.
 */
class AttemptLogin
{
    public function handle(string $phone, string $password, UserType $type, bool $remember = false, string $field = 'phone'): User
    {
        $phone = PhoneNumber::normalize($phone);
        $throttleKey = $this->throttleKey($type, $phone);

        $this->ensureIsNotRateLimited($throttleKey, $field);

        /** @var User|null $user */
        $user = User::where('phone', $phone)->where('type', $type)->first();

        // Always run a hash check so timing does not reveal whether the phone exists.
        $passwordMatches = Hash::check($password, $user?->password ?? '$2y$12$'.str_repeat('a', 53));

        if ($user === null || ! $passwordMatches) {
            RateLimiter::hit($throttleKey, config('store.login.decay_seconds'));

            throw ValidationException::withMessages([$field => __('auth.failed')]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([$field => __('auth.suspended')]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, $remember);
        session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return $user;
    }

    private function ensureIsNotRateLimited(string $key, string $field): void
    {
        if (! RateLimiter::tooManyAttempts($key, config('store.login.max_attempts'))) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            $field => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    private function throttleKey(UserType $type, string $phone): string
    {
        return Str::transliterate($type->value.'|'.$phone.'|'.request()->ip());
    }
}
