<?php

namespace Tests\Feature\Security;

use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Models\User;
use App\Otp\Contracts\OtpSender;
use App\Otp\OtpService;
use App\Otp\OtpThrottledException;
use App\Otp\OtpUnavailableException;
use App\Otp\Senders\LogOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{phone: string, code: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance('test-otp-sender', new class($this->sent) implements OtpSender
        {
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $code, OtpPurpose $purpose): void
            {
                $this->sent[] = ['phone' => $phone, 'code' => $code];
            }
        });

        config(['otp.driver' => 'test', 'otp.drivers.test' => 'test-otp-sender']);
        User::factory()->create(['phone' => '0599123456']);
    }

    private function otp(): OtpService
    {
        return app(OtpService::class);
    }

    public function test_codes_are_hashed_single_use_and_expire(): void
    {
        $this->otp()->sendCode('0599123456', OtpPurpose::PasswordReset, '1.1.1.1');
        $code = $this->sent[0]['code'];

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $row = OtpCode::first();
        $this->assertNotSame($code, $row->code_hash);
        $this->assertStringNotContainsString($code, json_encode($row->toArray()), 'the hash is hidden and the code never stored');

        $this->assertTrue($this->otp()->verifyCode('0599123456', OtpPurpose::PasswordReset, $code));
        $this->assertFalse($this->otp()->verifyCode('0599123456', OtpPurpose::PasswordReset, $code), 'one-time use');

        $this->travel(2)->minutes();
        $this->otp()->sendCode('0599123456', OtpPurpose::PasswordReset);
        $this->travel(11)->minutes();
        $this->assertFalse($this->otp()->verifyCode('0599123456', OtpPurpose::PasswordReset, $this->sent[1]['code']), 'expired');
    }

    public function test_wrong_attempts_burn_the_code_and_purposes_are_separate(): void
    {
        $this->otp()->sendCode('0599123456', OtpPurpose::PasswordReset);
        $code = $this->sent[0]['code'];

        $this->assertFalse($this->otp()->verifyCode('0599123456', OtpPurpose::PhoneVerification, $code), 'other purpose');

        foreach (range(1, 5) as $i) {
            $this->assertFalse($this->otp()->verifyCode('0599123456', OtpPurpose::PasswordReset, $code === '000000' ? '111111' : '000000'));
        }

        $this->assertFalse($this->otp()->verifyCode('0599123456', OtpPurpose::PasswordReset, $code), 'burned after max attempts');
    }

    public function test_a_new_code_invalidates_the_previous_one(): void
    {
        $this->otp()->sendCode('0599123456', OtpPurpose::PasswordReset);
        $this->travel(2)->minutes();
        $this->otp()->sendCode('0599123456', OtpPurpose::PasswordReset);

        $this->assertFalse($this->otp()->verifyCode('0599123456', OtpPurpose::PasswordReset, $this->sent[0]['code']));
        $this->assertTrue($this->otp()->verifyCode('0599123456', OtpPurpose::PasswordReset, $this->sent[1]['code']));
    }

    public function test_no_account_enumeration(): void
    {
        // Unknown number: same (silent) outcome, nothing sent, nothing stored.
        $this->otp()->sendCode('0599999999', OtpPurpose::PasswordReset);
        $this->assertSame([], $this->sent);
        $this->assertSame(0, OtpCode::count());

        // Phone change goes only to numbers that are NOT registered.
        $this->otp()->sendCode('0599123456', OtpPurpose::PhoneChange);
        $this->assertSame([], $this->sent);
        $this->otp()->sendCode('0599999999', OtpPurpose::PhoneChange);
        $this->assertCount(1, $this->sent);
    }

    public function test_sending_is_rate_limited(): void
    {
        $this->otp()->sendCode('0599123456', OtpPurpose::PasswordReset);

        $this->expectException(OtpThrottledException::class);
        $this->otp()->sendCode('0599123456', OtpPurpose::PasswordReset); // within the 60s cooldown
    }

    public function test_unconfigured_provider_is_reported_and_log_driver_refuses_production(): void
    {
        config(['otp.driver' => null]);
        $this->assertFalse($this->otp()->isAvailable());

        try {
            $this->otp()->sendCode('0599123456', OtpPurpose::PasswordReset);
            $this->fail('expected OtpUnavailableException');
        } catch (OtpUnavailableException) {
        }

        $this->app['env'] = 'production';
        $this->expectException(\RuntimeException::class);
        (new LogOtpSender)->send('0599123456', '123456', OtpPurpose::PasswordReset);
    }
}
