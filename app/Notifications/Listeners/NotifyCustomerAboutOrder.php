<?php

namespace App\Notifications\Listeners;

use App\Events\Orders\OrderEvent;
use App\Messaging\MessagingManager;
use App\Notifications\Customer\OrderStatusMessage;

/**
 * Sends the customer an order update on the channels that are both enabled
 * (a provider is configured) and wanted by the customer. With no provider
 * configured — the current state — nothing is queued at all.
 */
class NotifyCustomerAboutOrder
{
    public function __construct(private readonly MessagingManager $messaging) {}

    public function handle(OrderEvent $event): void
    {
        $customer = $event->order->user()->with('customer')->first();

        if ($customer === null) {
            return;
        }

        $channels = $this->messaging->channelsFor($customer);

        if ($channels !== []) {
            $customer->notify(new OrderStatusMessage($event->order, $event->order->status, $channels));
        }
    }
}
