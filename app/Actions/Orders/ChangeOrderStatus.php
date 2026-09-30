<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Events\Orders\OrderEvent;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Payments\PaymentManager;
use App\Services\Cart\QuantityRules;
use App\Support\Notifications\StockAlerts;
use Illuminate\Support\Facades\DB;

/**
 * Admin status changes. Allowed moves are defined by OrderStatus and
 * enforced by Order::transitionTo() (which also writes the status history).
 *
 * Stock policy: stock is deducted when the order is placed. When an order
 * is cancelled (only possible before delivery) the quantities go back to
 * stock exactly once — guarded by orders.stock_restored_at under a row lock —
 * and a pending cash-on-delivery payment is cancelled.
 */
class ChangeOrderStatus
{
    public function __construct(private readonly PaymentManager $payments) {}

    public function handle(Order $order, OrderStatus $status, ?User $by = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $status, $by, $note) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $from = $order->status;

            $order->transitionTo($status, $by, $note);

            if ($status === OrderStatus::Cancelled) {
                $this->restoreStock($order);
                $this->cancelPayment($order);
            }

            AuditLog::record($order, 'status_changed', ['status' => $from->value], ['status' => $status->value]);

            // Dispatched after commit: admin bell (cancellations) + future customer messages.
            event(OrderEvent::forStatus($status, $order, $by));

            return $order;
        });
    }

    private function restoreStock(Order $order): void
    {
        if ($order->stock_deducted_at === null || $order->stock_restored_at !== null) {
            return;
        }

        $items = $order->items()->whereNotNull('product_id')->orderBy('product_id')->lockForUpdate()->get();
        $products = Product::withTrashed()->whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach ($items as $item) {
            if ($product = $products->get($item->product_id)) {
                $stock = QuantityRules::toMilli((string) $product->stock_quantity) + QuantityRules::toMilli((string) $item->quantity);
                Product::withTrashed()->whereKey($product->id)->update(['stock_quantity' => QuantityRules::fromMilli($stock)]);
                $product->stock_quantity = QuantityRules::fromMilli($stock);
                StockAlerts::check($product->id); // re-arms alerts when stock recovers
            }
        }

        $order->forceFill(['stock_restored_at' => now()])->save();
    }

    private function cancelPayment(Order $order): void
    {
        foreach ($order->payments()->get() as $payment) {
            $this->payments->providerForExisting($payment->method)->cancel($payment);
        }
    }
}
