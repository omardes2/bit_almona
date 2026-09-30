<?php

namespace App\Messaging\Contracts;

use App\Messaging\OutgoingMessage;

/**
 * A transport for customer messages (a WhatsApp or SMS provider...).
 * Implementations must not log message bodies containing codes, and must
 * read credentials from config/.env only.
 */
interface MessageSender
{
    /** @param  string  $to  normalised phone (05XXXXXXXX) or address, depending on the channel */
    public function send(string $to, OutgoingMessage $message): void;
}
