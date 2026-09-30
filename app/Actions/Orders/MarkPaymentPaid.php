<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Payments\PaymentException;
use Illuminate\Support\Facades\DB;

/**
 * Cash on delivery: the admin confirms the cash was collected. Kept separate
 * from "delivered" on purpose — delivery does not imply payment.
 */
class MarkPaymentPaid
{
    public function handle(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $payment = $order->payment()->lockForUpdate()->first();

            if ($payment === null || $payment->status !== PaymentStatus::Pending) {
                throw new PaymentException('لا توجد دفعة بانتظار التحصيل لهذا الطلب.');
            }

            if ($order->status === OrderStatus::Cancelled) {
                throw new PaymentException('لا يمكن تسجيل دفعة لطلب ملغي.');
            }

            $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);

            AuditLog::record($payment, 'payment_status_changed', ['status' => PaymentStatus::Pending->value], ['status' => PaymentStatus::Paid->value, 'order' => $order->order_number]);
        });
    }
}
