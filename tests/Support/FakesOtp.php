<?php

namespace Tests\Support;

trait FakesOtp
{
    protected function fakeOtp(): FakeOtpSender
    {
        $sender = new FakeOtpSender;
        $this->app->instance(FakeOtpSender::class, $sender);
        config(['otp.driver' => 'fake', 'otp.drivers.fake' => FakeOtpSender::class]);

        return $sender;
    }
}
