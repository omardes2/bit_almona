<?php

namespace App\Services\Checkout;

use RuntimeException;

/**
 * Checkout was refused. Messages are Arabic and safe to show the customer.
 */
class CheckoutException extends RuntimeException
{
    /**
     * @param  list<string>  $problems  one line per product that needs attention
     */
    public function __construct(string $message, public readonly array $problems = [])
    {
        parent::__construct($message);
    }
}
