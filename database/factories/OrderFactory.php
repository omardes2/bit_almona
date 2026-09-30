<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'customer_name' => fake()->name(),
            'customer_phone' => '0599'.fake()->numerify('######'),
            'delivery_address' => 'الخليل - عين سارة',
            'currency' => 'ILS',
            'subtotal' => 0,
            'discount_total' => 0,
            'delivery_fee' => 0,
            'total' => 0,
        ];
    }
}
