<?php

namespace App\Messaging\Channels;

use App\Messaging\MessagingManager;
use Illuminate\Notifications\Notification;

/** Laravel notification channel: calls $notification->toWhatsApp($notifiable). */
class WhatsAppChannel
{
    public function __construct(private readonly MessagingManager $messaging) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $sender = $this->messaging->sender('whatsapp');

        if ($sender === null || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $to = $notifiable->customer?->whatsapp ?? $notifiable->phone;
        $sender->send($to, $notification->toWhatsApp($notifiable));
    }
}
