<?php

namespace App\Http\Middleware;

use App\Otp\OtpService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends customers with an unverified phone to /account/verify-phone — but
 * only when REQUIRE_PHONE_VERIFICATION=true AND an OTP sender exists.
 * Without a sender nobody could verify, so the requirement is skipped.
 */
class EnsurePhoneIsVerified
{
    public function __construct(private readonly OtpService $otp) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && self::enforced($this->otp) && ! $user->hasVerifiedPhone()) {
            if (! $request->isMethod('GET')) {
                abort(403, 'يجب توثيق رقم الجوال أولًا.');
            }

            session()->put('url.intended', $request->fullUrl());

            return redirect()->route('account.verify-phone');
        }

        return $next($request);
    }

    public static function enforced(OtpService $otp): bool
    {
        return config('store.require_phone_verification') && $otp->isAvailable();
    }
}
