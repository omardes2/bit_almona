<?php

use App\Payments\Providers\CashOnDeliveryProvider;

/*
|--------------------------------------------------------------------------
| Payment methods
|--------------------------------------------------------------------------
|
| One entry per App\Enums\PaymentMethod value, shown at checkout in this
| order when "enabled" is true and the provider class exists. The browser can
| only pick one of the enabled keys; it never sends amounts or statuses.
|
| Adding an online gateway later (see docs/PAYMENTS.md):
|   1. add a PaymentMethod enum case (label + description),
|   2. write a provider implementing App\Payments\Contracts\PaymentProvider
|      (isOnline() = true, redirectUrl() returns the hosted payment page),
|   3. add it below with 'enabled' => env('PAYMENT_XXX_ENABLED', false).
| Credentials go in .env only — never in this file, git, or the admin UI.
|
*/

return [

    'methods' => [
        'cash_on_delivery' => [
            'enabled' => (bool) env('PAYMENT_COD_ENABLED', true),
            'provider' => CashOnDeliveryProvider::class,
            'icon' => 'cash',
        ],
    ],

];
