<?php

namespace Database\Seeders;

use App\Actions\Auth\CreateAdmin;
use App\Enums\AdminRole;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;

/**
 * Creates the first super admin from SEED_ADMIN_PHONE / SEED_ADMIN_PASSWORD
 * in .env. Nothing is hard-coded here; if the variables are empty the seeder
 * is skipped and the admin can be created with `php artisan store:create-admin`.
 */
class AdminSeeder extends Seeder
{
    public function run(CreateAdmin $createAdmin): void
    {
        $phone = PhoneNumber::normalize(config('store.seed_admin.phone'));
        $password = config('store.seed_admin.password');

        if (! PhoneNumber::isValid($phone) || blank($password)) {
            $this->command?->warn('تم تخطي إنشاء المدير: عيّن SEED_ADMIN_PHONE و SEED_ADMIN_PASSWORD في .env أو استخدم store:create-admin');

            return;
        }

        if (User::where('phone', $phone)->exists()) {
            return;
        }

        $createAdmin->handle(config('store.seed_admin.name'), $phone, $password, AdminRole::SuperAdmin);
    }
}
