<?php

use App\Messaging\Senders\LogMessageSender;

/*
|--------------------------------------------------------------------------
| Customer messaging (WhatsApp / SMS / Email / Push)
|--------------------------------------------------------------------------
|
| No real provider is integrated yet. A channel is used only when its
| driver is set. Available drivers today:
|   null  — channel disabled (default)
|   log   — writes the message to the application log (development only)
| A real provider (e.g. a WhatsApp Business API) is added later as a new
| App\Messaging\Contracts\MessageSender implementation + a driver name here.
| Credentials always come from .env — never from this file or git.
|
*/

return [

    'channels' => [
        'whatsapp' => ['driver' => env('WHATSAPP_PROVIDER')],
        'sms' => ['driver' => env('SMS_PROVIDER')],
        'email' => ['driver' => env('CUSTOMER_EMAIL_NOTIFICATIONS') ? 'mail' : null],
        'push' => ['driver' => env('PUSH_PROVIDER')],
    ],

    'drivers' => [
        'log' => LogMessageSender::class,
    ],

    // Used when a customer has not chosen their own preferences.
    'customer_defaults' => [
        'whatsapp' => true,
        'sms' => false,
        'email' => false,
        'push' => false,
    ],

    // Queue used for outgoing customer messages (never slows down checkout).
    'queue' => env('MESSAGING_QUEUE', 'notifications'),

];
