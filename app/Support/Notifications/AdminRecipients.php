<?php

namespace App\Support\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

final class AdminRecipients
{
    /**
     * Active admins allowed to $ability (e.g. manage-orders, manage-catalog).
     *
     * @return Collection<int, User>
     */
    public static function for(string $ability): Collection
    {
        return User::query()->admins()->active()->with('admin')->get()
            ->filter(fn (User $admin) => Gate::forUser($admin)->allows($ability))
            ->values();
    }
}
