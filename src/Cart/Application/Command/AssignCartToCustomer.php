<?php

declare(strict_types=1);

namespace App\Cart\Application\Command;

/**
 * AssignCartToCustomer Command.
 *
 * Assigns a guest cart to a customer (e.g., after login)
 * This allows guests to keep their cart items when they authenticate
 */
final readonly class AssignCartToCustomer
{
    public function __construct(
        public string $cartId,
        public string $customerId,
    ) {
    }
}
