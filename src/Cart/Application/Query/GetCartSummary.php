<?php

declare(strict_types=1);

namespace App\Cart\Application\Query;

/**
 * GetCartSummary Query.
 *
 * Retrieves lightweight cart summary (for UI badges, headers, etc.)
 */
final readonly class GetCartSummary
{
    public function __construct(
        public string $cartId
    ) {
    }
}
