<?php

namespace App\Payments\Providers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Contracts\PaymentProvider;
use App\Payments\PaymentException;

/**
 * Cash on delivery: the payment stays "pending" until an admin confirms the
 * cash was collected (App\Actions\Orders\MarkPaymentPaid). Delivering the
 * order does NOT mark it paid automatically.
 */
class CashOnDeliveryProvider implements PaymentProvider
{
    public function method(): PaymentMethod
    {
        return PaymentMethod::CashOnDelivery;
    }

    public function createPayment(Order $order): Payment
    {
        return $order->payments()->create([
            'provider' => $this->method()->value,
            'method' => $this->method(),
            'status' => PaymentStatus::Pending,
            'amount' => $order->total,
            'currency' => $order->currency,
        ]);
    }

    public function verifyPayment(Payment $payment): PaymentStatus
    {
        // Nothing to ask remotely: the status is whatever the store recorded.
        return $payment->status;
    }

    public function refund(Payment $payment, ?string $amount = null): Payment
    {
        if ($payment->status !== PaymentStatus::Paid) {
            throw new PaymentException('لا يمكن استرجاع مبلغ لم يُدفع بعد.');
        }

        // Cash refunds are handed back in person; we only record them.
        $payment->update([
            'status' => PaymentStatus::Refunded,
            'meta' => array_merge($payment->meta ?? [], ['refunded_amount' => $amount ?? (string) $payment->amount]),
        ]);

        return $payment;
    }

    public function cancel(Payment $payment): Payment
    {
        if ($payment->status === PaymentStatus::Pending) {
            $payment->update(['status' => PaymentStatus::Cancelled]);
        }

        return $payment;
    }
}
