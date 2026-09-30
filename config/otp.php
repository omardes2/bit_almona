<?php

use App\Otp\Senders\LogOtpSender;

return [

    /*
    | How codes are delivered: null (not configured — no OTP flow can run yet)
    | or "log" (development only, writes the code to the log). A real WhatsApp
    | or SMS provider is added later as an App\Otp\Contracts\OtpSender.
    */
    'driver' => env('OTP_DRIVER'),

    'drivers' => [
        'log' => LogOtpSender::class,
    ],

    'length' => 6,
    'ttl_minutes' => 10,
    'max_attempts' => 5,          // wrong guesses per code, then it is burned
    'resend_cooldown_seconds' => 60,
    'max_sends_per_hour' => 5,     // per phone + purpose
    'max_sends_per_ip_per_hour' => 20,

];
