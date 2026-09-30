<?php

namespace Database\Factories;

use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryZone>
 */
class DeliveryZoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'منطقة '.fake()->unique()->numberBetween(1, 9999),
            'delivery_fee' => 10,
            'min_order_amount' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
