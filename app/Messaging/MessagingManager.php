<?php

namespace App\Messaging;

use App\Messaging\Contracts\MessageSender;
use App\Models\User;

class MessagingManager
{
    /** Channels whose driver is configured (not null). */
    public function isEnabled(string $channel): bool
    {
        return filled(config("messaging.channels.{$channel}.driver"));
    }

    public function sender(string $channel): ?MessageSender
    {
        $driver = config("messaging.channels.{$channel}.driver");
        $class = $driver ? config("messaging.drivers.{$driver}") : null;

        return $class ? app()->make($class, ['channel' => $channel]) : null;
    }

    /**
     * Channels a customer should be messaged on: enabled in config AND wanted
     * by the customer (their saved preference, or the store default).
     *
     * @return list<string> keys: whatsapp | sms | email | push
     */
    public function channelsFor(User $user): array
    {
        $preferences = array_merge(
            config('messaging.customer_defaults', []),
            (array) ($user->customer?->notification_preferences ?? []),
        );

        return array_values(array_filter(
            array_keys($preferences),
            fn (string $channel) => $preferences[$channel] && $this->isEnabled($channel) && $this->hasAddress($user, $channel),
        ));
    }

    private function hasAddress(User $user, string $channel): bool
    {
        return match ($channel) {
            'whatsapp' => filled($user->customer?->whatsapp ?? $user->phone),
            'sms' => filled($user->phone),
            'email' => filled($user->email),
            default => false, // push: needs device tokens (future)
        };
    }
}
