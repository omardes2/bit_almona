<?php

namespace Tests\Support;

use App\Models\Payment;
use App\Payments\Providers\CashOnDeliveryProvider;

/**
 * Test double that behaves like a future online gateway (redirects to a
 * hosted page). No real gateway exists in the application.
 */
class FakeOnlinePaymentProvider extends CashOnDeliveryProvider
{
    public static string $url = 'https://pay.example.test/checkout/123';

    public function isOnline(): bool
    {
        return true;
    }

    public function redirectUrl(Payment $payment): ?string
    {
        return static::$url;
    }
}
