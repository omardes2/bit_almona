<?php

namespace App\Notifications\Admin;

use App\Models\Product;
use App\Support\Decimal;

class ProductLowStock extends AdminNotification
{
    public function __construct(public Product $product) {}

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'low_stock',
            'level' => 'warning',
            'title' => 'مخزون منخفض: '.$this->product->name,
            'body' => 'المتبقي '.Decimal::trim((string) $this->product->stock_quantity).' '.$this->product->unit->label()
                .' (حد التنبيه '.Decimal::trim((string) $this->product->low_stock_threshold).')',
            'url' => route('admin.products.edit', $this->product->id, absolute: false),
        ];
    }
}
