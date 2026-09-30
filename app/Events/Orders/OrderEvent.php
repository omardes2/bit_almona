<?php

namespace App\Events\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base for order lifecycle events. ShouldDispatchAfterCommit: when fired
 * inside a transaction the event is held until COMMIT and dropped on
 * ROLLBACK, so nobody is ever notified about an order that does not exist.
 *
 * Order code only fires these; who gets told, and how (database, WhatsApp,
 * SMS, email...), is decided by listeners — order logic never changes.
 */
abstract class OrderEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public ?User $actor = null,
    ) {}

    /** The event to fire when an order moves into $status. */
    public static function forStatus(OrderStatus $status, Order $order, ?User $actor = null): ?self
    {
        return match ($status) {
            OrderStatus::New => new OrderPlaced($order, $actor),
            OrderStatus::Confirmed => new OrderConfirmed($order, $actor),
            OrderStatus::Preparing => new OrderPreparing($order, $actor),
            OrderStatus::OutForDelivery => new OrderOutForDelivery($order, $actor),
            OrderStatus::Delivered => new OrderDelivered($order, $actor),
            OrderStatus::Cancelled => new OrderCancelled($order, $actor),
        };
    }

    /** @return list<class-string<self>> */
    public static function all(): array
    {
        return [OrderPlaced::class, OrderConfirmed::class, OrderPreparing::class, OrderOutForDelivery::class, OrderDelivered::class, OrderCancelled::class];
    }
}
