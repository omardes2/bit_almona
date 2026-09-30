<?php

namespace App\Actions\Auth;

use App\Enums\AccountStatus;
use App\Enums\AdminRole;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateAdmin
{
    public function handle(
        string $name,
        string $phone,
        string $password,
        AdminRole $role = AdminRole::Staff,
        AccountStatus $status = AccountStatus::Active,
    ): User {
        return DB::transaction(function () use ($name, $phone, $password, $role, $status) {
            $user = User::create([
                'name' => $name,
                'phone' => $phone,
                'password' => $password,
                'type' => UserType::Admin,
                'status' => $status,
            ]);

            $user->admin()->create(['role' => $role]);

            return $user;
        });
    }
}
