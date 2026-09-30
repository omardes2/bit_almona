<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            StoreSettingsSeeder::class,
            AdminSeeder::class,
        ]);

        if (config('store.seed_demo_data') && ! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
