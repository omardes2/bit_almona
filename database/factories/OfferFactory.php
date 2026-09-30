<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'original_price' => 20,
            'offer_price' => 15,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'starts_at' => now()->subWeeks(2),
            'ends_at' => now()->subMinute(),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addWeek(),
        ]);
    }
}
