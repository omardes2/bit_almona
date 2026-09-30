<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

/**
 * Customers only ever see and change their own addresses.
 */
class AddressPolicy
{
    public function view(User $user, Address $address): bool
    {
        return $address->user_id === $user->id;
    }

    public function update(User $user, Address $address): bool
    {
        return $address->user_id === $user->id;
    }

    public function delete(User $user, Address $address): bool
    {
        return $address->user_id === $user->id;
    }
}
