<?php

use App\Payments\Providers\CashOnDeliveryProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled payment methods (shown at checkout, in this order)
    |--------------------------------------------------------------------------
    |
    | Values of App\Enums\PaymentMethod. Online gateways will be added here
    | later together with their provider class; their credentials must live
    | in .env only (never in this file or in git).
    |
    */

    'enabled' => ['cash_on_delivery'],

    'providers' => [
        'cash_on_delivery' => CashOnDeliveryProvider::class,
    ],

];
