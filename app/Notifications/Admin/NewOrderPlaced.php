<?php

namespace App\Notifications\Admin;

use App\Models\Order;
use App\Support\Money;

class NewOrderPlaced extends AdminNotification
{
    public function __construct(public Order $order) {}

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'order_placed',
            'level' => 'info',
            'title' => 'طلب جديد '.$this->order->order_number,
            'body' => $this->order->customer_name.' — '.Money::format($this->order->total).' — '.$this->order->delivery_zone_name,
            'url' => route('admin.orders.show', $this->order, absolute: false),
        ];
    }
}
