<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Store name fallback
    |--------------------------------------------------------------------------
    |
    | Used only when the store_name setting is missing. Always read the name
    | through App\Support\Store::name().
    |
    */

    'name' => 'بيت المونة',

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

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    */

    'images' => [
        'disk' => env('STORE_IMAGES_DISK', 'public'),
        'max_kilobytes' => 4096,
        'min_dimension' => 100,
        'max_dimension' => 4000,
        // Originals are downscaled to this size and re-encoded (strips EXIF / embedded payloads).
        'original_max_dimension' => 1600,
        'thumbnail_size' => 400,
    ],

    'admin_per_page' => 15,

    /*
    | Require a verified phone (OTP) before checkout. Only enforced when this
    | is true AND an OTP sender is configured (config/otp.php); otherwise
    | customers could never check out.
    */
    // Where the server's backup job writes database dumps (never inside public/).
    // Only read by `php artisan store:backup-status`; this app does not create dumps.
    'backup_path' => env('BACKUP_PATH'),

    'require_phone_verification' => (bool) env('REQUIRE_PHONE_VERIFICATION', false),

    'login' => [
        'max_attempts' => 5,
        'decay_seconds' => 60,
    ],

];
