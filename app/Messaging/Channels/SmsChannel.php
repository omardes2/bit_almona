<?php

namespace App\Messaging\Channels;

use App\Messaging\MessagingManager;
use Illuminate\Notifications\Notification;

/** Laravel notification channel: calls $notification->toSms($notifiable). */
class SmsChannel
{
    public function __construct(private readonly MessagingManager $messaging) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $sender = $this->messaging->sender('sms');

        if ($sender === null || ! method_exists($notification, 'toSms')) {
            return;
        }

        $sender->send($notifiable->phone, $notification->toSms($notifiable));
    }
}
