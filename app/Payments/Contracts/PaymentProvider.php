<?php

namespace App\Payments\Contracts;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;

/**
 * Contract every payment method implements. Cash on delivery is the first
 * provider; an online gateway later adds a class here and an entry in
 * config/payments.php — checkout and order code do not change.
 */
interface PaymentProvider
{
    public function method(): PaymentMethod;

    /**
     * True when the customer pays instantly online (card, wallet...); false
     * when payment is collected later (cash on delivery).
     */
    public function isOnline(): bool;

    /**
     * Called AFTER the order transaction commits. An online gateway returns
     * its hosted payment page (https) and the checkout redirects there; the
     * payment stays pending until the gateway's signed webhook confirms it
     * (see docs/PAYMENTS.md). Offline methods return null.
     */
    public function redirectUrl(Payment $payment): ?string;

    /**
     * Create the payment record for a new order. Called inside the order
     * transaction, so it must not perform slow network calls (a gateway
     * would create a pending record here and redirect afterwards).
     */
    public function createPayment(Order $order): Payment;

    /** Ask the provider for the real status of a payment. */
    public function verifyPayment(Payment $payment): PaymentStatus;

    /** Refund a paid payment (fully when $amount is null). */
    public function refund(Payment $payment, ?string $amount = null): Payment;

    /** The order was cancelled: void a payment that has not been collected. */
    public function cancel(Payment $payment): Payment;
}
