<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\AdminRole;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * A customer by default. Use ->admin() for admin accounts.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '059'.fake()->unique()->numerify('#######'),
            'email' => null,
            'password' => static::$password ??= Hash::make('password1'),
            'type' => UserType::Customer,
            'status' => AccountStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->isCustomer() && ! $user->customer()->exists()) {
                $user->customer()->create(['whatsapp' => $user->phone]);
            }
        });
    }

    public function admin(AdminRole $role = AdminRole::SuperAdmin): static
    {
        return $this->state(['type' => UserType::Admin])
            ->afterCreating(fn (User $user) => $user->admin()->create(['role' => $role]));
    }

    public function suspended(): static
    {
        return $this->state(['status' => AccountStatus::Suspended]);
    }
}
