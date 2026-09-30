<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Payments\Contracts\PaymentProvider;

class PaymentManager
{
    /**
     * @return list<PaymentMethod>
     */
    public function enabledMethods(): array
    {
        return array_values(array_filter(array_map(
            fn (string $value) => PaymentMethod::tryFrom($value),
            config('payments.enabled', []),
        )));
    }

    public function isEnabled(PaymentMethod $method): bool
    {
        return in_array($method, $this->enabledMethods(), true);
    }

    public function provider(PaymentMethod $method): PaymentProvider
    {
        $class = config("payments.providers.{$method->value}");

        if (! $class || ! $this->isEnabled($method)) {
            throw new PaymentException('طريقة الدفع غير متاحة.');
        }

        return app($class);
    }
}
