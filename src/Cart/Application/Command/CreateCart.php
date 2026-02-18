<?php

declare(strict_types=1);

namespace App\Cart\Application\Command;

/**
 * CreateCart Command.
 *
 * Creates a new cart for a customer or guest session
 */
final readonly class CreateCart
{
    public function __construct(
        public string $cartId,
        public string $tenantId,
        public ?string $customerId = null, // Null for guest carts
        public ?string $sessionId = null,   // Required for guest carts
    ) {
    }
}
