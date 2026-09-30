<?php

namespace App\Notifications\Admin;

use App\Models\Product;

class ProductOutOfStock extends AdminNotification
{
    public function __construct(public Product $product) {}

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'out_of_stock',
            'level' => 'danger',
            'title' => 'نفد مخزون المنتج',
            'body' => $this->product->name,
            'url' => route('admin.products.edit', $this->product->id, absolute: false),
        ];
    }
}
