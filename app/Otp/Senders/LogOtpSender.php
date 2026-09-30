<?php

namespace App\Otp\Senders;

use App\Enums\OtpPurpose;
use App\Otp\Contracts\OtpSender;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Development only: writes the code to the log. Refuses to run in production.
 */
class LogOtpSender implements OtpSender
{
    public function send(string $phone, string $code, OtpPurpose $purpose): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The log OTP driver cannot be used in production.');
        }

        Log::debug("[otp:{$purpose->value}] {$phone} => {$code}");
    }
}
