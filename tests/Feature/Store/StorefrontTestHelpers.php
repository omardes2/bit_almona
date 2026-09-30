<?php

namespace Tests\Feature\Store;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;

trait StorefrontTestHelpers
{
    protected function product(array $attributes = [], ?Category $category = null): Product
    {
        return Product::factory()
            ->for($category ?? Category::factory()->create())
            ->create(array_merge([
                'original_price' => 20,
                'sale_price' => 20,
                'stock_quantity' => 10,
                'min_order_quantity' => 1,
                'quantity_step' => 1,
                'status' => ProductStatus::Available,
            ], $attributes));
    }
}
