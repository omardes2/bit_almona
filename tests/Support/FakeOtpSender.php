<?php

namespace Tests\Support;

use App\Enums\OtpPurpose;
use App\Otp\Contracts\OtpSender;

/**
 * Test-only OTP sender: records codes instead of sending them.
 */
class FakeOtpSender implements OtpSender
{
    /** @var list<array{phone: string, code: string, purpose: OtpPurpose}> */
    public array $sent = [];

    public function send(string $phone, string $code, OtpPurpose $purpose): void
    {
        $this->sent[] = ['phone' => $phone, 'code' => $code, 'purpose' => $purpose];
    }

    public function lastCodeFor(string $phone): ?string
    {
        $matches = array_values(array_filter($this->sent, fn (array $s) => $s['phone'] === $phone));

        return $matches === [] ? null : end($matches)['code'];
    }

    public function sentTo(string $phone): int
    {
        return count(array_filter($this->sent, fn (array $s) => $s['phone'] === $phone));
    }
}
