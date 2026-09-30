<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Fallback values only. The live values are stored in the store_settings
    | table and can be changed from the admin panel.
    |
    */

    'currency' => [
        'code' => 'ILS',
        'symbol' => '₪',
    ],

    /*
    |--------------------------------------------------------------------------
    | Initial admin account (used by the seeders only)
    |--------------------------------------------------------------------------
    */

    'seed_admin' => [
        'name' => env('SEED_ADMIN_NAME', 'مدير المتجر'),
        'phone' => env('SEED_ADMIN_PHONE'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],

    'seed_demo_data' => (bool) env('SEED_DEMO_DATA', false),

    /*
    |--------------------------------------------------------------------------
    | Login rate limiting
    |--------------------------------------------------------------------------
    */

    'login' => [
        'max_attempts' => 5,
        'decay_seconds' => 60,
    ],

];
