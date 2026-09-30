<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\SaleUnit;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 2, 100);

        return [
            'category_id' => Category::factory(),
            'name' => 'منتج '.fake()->unique()->numberBetween(1, 99999),
            'slug' => 'product-'.Str::lower(Str::random(10)),
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'original_price' => $price,
            'sale_price' => $price,
            'stock_quantity' => 50,
            'min_order_quantity' => 1,
            'quantity_step' => 1,
            'unit' => SaleUnit::Piece,
            'status' => ProductStatus::Available,
        ];
    }

    public function status(ProductStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
