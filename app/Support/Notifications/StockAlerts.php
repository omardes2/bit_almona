<?php

namespace App\Support\Notifications;

use App\Models\Product;
use App\Notifications\Admin\ProductLowStock;
use App\Notifications\Admin\ProductOutOfStock;
use App\Services\Cart\QuantityRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Low / out-of-stock alerts without spam.
 *
 * State lives on the product (low_stock_notified_at, out_of_stock_notified_at):
 *   stock > threshold        => both flags cleared (alerts re-armed), nothing sent
 *   0 < stock <= threshold   => "low stock" sent once (out-of-stock flag cleared)
 *   stock <= 0               => "out of stock" sent once (low flag also set, so a
 *                               small restock below the threshold stays quiet)
 * So a product that keeps selling below its threshold alerts once; it alerts
 * again only after going back above the threshold and dropping again.
 *
 * Flag updates run in the caller's transaction; the notifications are sent
 * after commit (DB::afterCommit), so a rolled-back order never alerts.
 */
final class StockAlerts
{
    public static function check(int $productId): void
    {
        $product = Product::withTrashed()->find($productId);

        if ($product === null || $product->trashed()) {
            return;
        }

        $stock = QuantityRules::toMilli((string) $product->stock_quantity) ?? 0;
        $threshold = QuantityRules::toMilli((string) $product->low_stock_threshold) ?? 0;

        $send = null;
        $flags = [];

        if ($stock <= 0) {
            if ($product->out_of_stock_notified_at === null) {
                $send = new ProductOutOfStock($product);
                $flags['out_of_stock_notified_at'] = now();
            }
            $flags['low_stock_notified_at'] = $product->low_stock_notified_at ?? now();
        } elseif ($stock <= $threshold) {
            if ($product->low_stock_notified_at === null) {
                $send = new ProductLowStock($product);
                $flags['low_stock_notified_at'] = now();
            }
            $flags['out_of_stock_notified_at'] = null;
        } else {
            $flags = ['low_stock_notified_at' => null, 'out_of_stock_notified_at' => null];
        }

        // Only write flags whose "set / not set" state changes. Plain update:
        // no model events, no audit noise, no recursion.
        $changes = array_filter($flags, fn ($value, $key) => ($value === null) !== ($product->{$key} === null), ARRAY_FILTER_USE_BOTH);

        if ($changes !== []) {
            Product::withTrashed()->whereKey($product->id)->update($changes);
        }

        if ($send !== null) {
            DB::afterCommit(fn () => Notification::send(AdminRecipients::for('manage-catalog'), $send));
        }
    }
}
