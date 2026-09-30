<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * unit_price_at_add is informational only (to tell the customer that a
 * price changed); totals are always recalculated with ProductPriceResolver.
 */
#[Fillable(['cart_id', 'product_id', 'quantity', 'unit_price_at_add'])]
class CartItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price_at_add' => 'decimal:2',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        // Trashed products stay visible in the cart as "no longer available".
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
