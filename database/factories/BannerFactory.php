<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'بنر '.fake()->unique()->numberBetween(1, 9999),
            'image' => 'banners/test.jpg',
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(['starts_at' => now()->subWeek(), 'ends_at' => now()->subMinute()]);
    }

    public function scheduled(): static
    {
        return $this->state(['starts_at' => now()->addDay(), 'ends_at' => null]);
    }
}
