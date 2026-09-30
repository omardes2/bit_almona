<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Customers see their own orders; active admins with order access see all.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return $user->can('manage-orders');
        }

        return $order->user_id !== null && $order->user_id === $user->id;
    }
}
