<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    /** /account/orders/{order} */
    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        return view('account.order', ['order' => $this->load($order), 'confirmation' => false]);
    }

    /** /order-confirmed/{order} — same data, "thank you" framing. */
    public function confirmed(Order $order): View
    {
        Gate::authorize('view', $order);

        return view('account.order', ['order' => $this->load($order), 'confirmation' => true]);
    }

    private function load(Order $order): Order
    {
        return $order->load(['items', 'payment', 'statusHistory']);
    }
}
