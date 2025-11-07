<?php

declare(strict_types=1);

namespace App\Cart\Application\Command;

/**
 * UpdateCartQuantity Command.
 *
 * Updates the quantity of a specific cart item
 */
final readonly class UpdateCartQuantity
{
    public function __construct(
        public string $cartId,
        public string $cartItemId,
        public int $newQuantity
    ) {
    }
}
