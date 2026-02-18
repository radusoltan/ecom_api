<?php

declare(strict_types=1);

namespace App\Cart\Application\Command;

/**
 * ClearCart Command.
 *
 * Removes all items from the cart
 */
final readonly class ClearCart
{
    public function __construct(
        public string $cartId,
    ) {
    }
}
