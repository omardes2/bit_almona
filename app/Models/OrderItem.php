<?php

namespace App\Models;

use App\Enums\SaleUnit;
use App\Services\Pricing\ProductPriceResolver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An order line. Name, SKU, unit and prices are a snapshot taken when the
 * order is placed, so later product/price changes never alter old orders.
 */
#[Fillable([
    'order_id', 'product_id', 'offer_id', 'product_name', 'product_sku', 'unit',
    'original_unit_price', 'unit_price', 'quantity', 'line_total',
])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'unit' => SaleUnit::class,
            'original_unit_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'quantity' => 'decimal:3',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * Build an (unsaved) order line from the product's current server-side
     * price. Any price sent by the browser is ignored.
     */
    public static function snapshotFrom(Product $product, float $quantity): self
    {
        $price = app(ProductPriceResolver::class)->resolve($product);

        return new self([
            'product_id' => $product->id,
            'offer_id' => $price->offerId,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit' => $product->unit,
            'original_unit_price' => $price->originalPrice,
            'unit_price' => $price->finalPrice,
            'quantity' => $quantity,
            'line_total' => round($price->finalPrice * $quantity, 2),
        ]);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
