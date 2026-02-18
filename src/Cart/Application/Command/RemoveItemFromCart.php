<?php

declare(strict_types=1);

namespace App\Cart\Application\Command;

/**
 * RemoveItemFromCart Command.
 *
 * Removes a specific item from the cart
 */
final readonly class RemoveItemFromCart
{
    public function __construct(
        public string $cartId,
        public string $cartItemId,
    ) {
    }
}
