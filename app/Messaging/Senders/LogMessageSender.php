<?php

namespace App\Messaging\Senders;

use App\Messaging\Contracts\MessageSender;
use App\Messaging\OutgoingMessage;
use Illuminate\Support\Facades\Log;

/**
 * Development driver: writes the message to the log instead of sending it.
 * Not a real provider — never enable it in production.
 */
class LogMessageSender implements MessageSender
{
    public function __construct(private readonly string $channel = 'message') {}

    public function send(string $to, OutgoingMessage $message): void
    {
        Log::info("[{$this->channel}] message to {$to}", ['template' => $message->template, 'text' => $message->text]);
    }
}
