<?php

namespace App\Messaging;

final readonly class OutgoingMessage
{
    /**
     * @param  array<string, string>  $data  template variables for providers that use approved templates (e.g. WhatsApp)
     */
    public function __construct(
        public string $text,
        public ?string $template = null,
        public array $data = [],
    ) {}
}
