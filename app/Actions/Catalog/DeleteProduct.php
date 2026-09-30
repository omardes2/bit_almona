<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Products are soft deleted: old order_items keep their snapshot and their
 * product_id link, image files are kept so the product can be restored,
 * and the product's offers are switched off so they stop applying.
 */
class DeleteProduct
{
    public function handle(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product->offers()->where('is_active', true)->get()->each->update(['is_active' => false]);
            $product->delete();
        });
    }
}
