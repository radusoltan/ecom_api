<?php

declare(strict_types=1);

namespace App\Cart\Application\Query;

/**
 * GetCart Query.
 *
 * Retrieves full cart details with all items
 */
final readonly class GetCart
{
    public function __construct(
        public string $cartId,
    ) {
    }
}
