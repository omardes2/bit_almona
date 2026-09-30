<?php

namespace App\Services\Cart;

use RuntimeException;

/**
 * A cart action was refused. The message is Arabic and safe to show to the customer.
 */
class CartException extends RuntimeException {}
