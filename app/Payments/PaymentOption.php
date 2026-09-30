<?php

namespace App\Payments;

use App\Enums\PaymentMethod;

/**
 * What checkout shows for one enabled payment method.
 */
final readonly class PaymentOption
{
    public function __construct(
        public PaymentMethod $method,
        public string $label,
        public string $description,
        public ?string $icon,
        public bool $online,
    ) {}

    public function timingLabel(): string
    {
        return $this->online ? 'دفع فوري عبر الإنترنت' : 'الدفع عند الاستلام';
    }
}
