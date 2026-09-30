<?php

namespace App\Otp\Contracts;

use App\Enums\OtpPurpose;

/**
 * Delivers a one-time code (WhatsApp, SMS...). Implementations must never
 * log the code in production.
 */
interface OtpSender
{
    public function send(string $phone, string $code, OtpPurpose $purpose): void;
}
