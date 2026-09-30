<?php

namespace App\Notifications\Admin;

use App\Models\Order;

class OrderWasCancelled extends AdminNotification
{
    public function __construct(public Order $order) {}

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'order_cancelled',
            'level' => 'warning',
            'title' => 'ألغي الطلب '.$this->order->order_number,
            'body' => $this->order->cancellation_reason ?: $this->order->customer_name,
            'url' => route('admin.orders.show', $this->order, absolute: false),
        ];
    }
}
