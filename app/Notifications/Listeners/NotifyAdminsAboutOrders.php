<?php

namespace App\Notifications\Listeners;

use App\Events\Orders\OrderCancelled;
use App\Events\Orders\OrderEvent;
use App\Events\Orders\OrderPlaced;
use App\Notifications\Admin\NewOrderPlaced;
use App\Notifications\Admin\OrderWasCancelled;
use App\Support\Notifications\AdminRecipients;
use Illuminate\Support\Facades\Notification;

/**
 * Bell notifications for admins who handle orders. Runs after the order
 * transaction has committed (see OrderEvent).
 */
class NotifyAdminsAboutOrders
{
    public function handle(OrderEvent $event): void
    {
        $notification = match (true) {
            $event instanceof OrderPlaced => new NewOrderPlaced($event->order),
            $event instanceof OrderCancelled => new OrderWasCancelled($event->order),
            default => null,
        };

        if ($notification === null) {
            return;
        }

        // Don't notify the admin who did it (e.g. cancelled it themselves).
        $recipients = AdminRecipients::for('manage-orders')->reject(fn ($admin) => $admin->id === $event->actor?->id);

        Notification::send($recipients, $notification);
    }
}
