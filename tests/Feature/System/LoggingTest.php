<?php

namespace Tests\Feature\System;

use App\Logging\RedactSensitiveData;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class LoggingTest extends TestCase
{
    public function test_every_file_and_stream_channel_is_redacted(): void
    {
        foreach (['single', 'daily', 'monthly', 'stderr', 'syslog', 'errorlog'] as $channel) {
            $this->assertContains(RedactSensitiveData::class, config("logging.channels.{$channel}.tap", []), $channel);
        }
    }

    public function test_secrets_are_masked_before_they_reach_the_log_file(): void
    {
        $path = storage_path('logs/redaction-test.log');
        @unlink($path);
        config(['logging.channels.redaction_test' => [
            'driver' => 'single',
            'path' => $path,
            'tap' => [RedactSensitiveData::class],
        ]]);

        Log::channel('redaction_test')->error('login failed password=hunter2 Bearer abc.def.ghi', [
            'password' => 'hunter2',
            'order_id' => 55,
            'otp' => '123456',
            'nested' => ['api_key' => 'sk_live_123', 'note' => 'token: tok_999'],
            'session_id' => 'sess-abc',
        ]);

        $written = file_get_contents($path);
        @unlink($path);

        foreach (['hunter2', '123456', 'sk_live_123', 'tok_999', 'abc.def.ghi', 'sess-abc'] as $secret) {
            $this->assertStringNotContainsString($secret, $written);
        }
        $this->assertStringContainsString('"order_id":55', $written);
        $this->assertStringContainsString(RedactSensitiveData::MASK, $written);
    }

    public function test_failed_jobs_are_logged_with_safe_context(): void
    {
        Log::spy();

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('resolveName')->andReturn('App\\Notifications\\OrderStatusMessage');
        $job->shouldReceive('getQueue')->andReturn('notifications');

        event(new JobFailed('database', $job, new RuntimeException('provider down')));

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context) => $message === 'queue.job_failed'
            && $context['job'] === 'App\\Notifications\\OrderStatusMessage'
            && $context['queue'] === 'notifications'
            && str_contains($context['error'], 'provider down')
            && ! array_key_exists('payload', $context));
    }
}
