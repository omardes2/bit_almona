<?php

namespace App\Actions\Auth;

use App\Enums\AccountStatus;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterCustomer
{
    /**
     * @param  array{name: string, phone: string, whatsapp?: ?string, password: string}  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'type' => UserType::Customer,
                'status' => AccountStatus::Active,
            ]);

            $user->customer()->create([
                'whatsapp' => ($data['whatsapp'] ?? null) ?: $data['phone'],
            ]);

            return $user;
        });
    }
}
