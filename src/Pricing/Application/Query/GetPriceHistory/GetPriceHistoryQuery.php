<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetPriceHistory;

/**
 * Query to get price history for a product.
 */
final readonly class GetPriceHistoryQuery
{
    public function __construct(
        public string $productId,
        public string $tenantId,
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}
