<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetLowestPriceInLastDays;

/**
 * Query to get the lowest price for a product in the last N days.
 * Required for EU consumer protection laws (must show lowest price in last 30 days).
 */
final readonly class GetLowestPriceInLastDaysQuery
{
    public function __construct(
        public string $productId,
        public string $tenantId,
        public int $days = 30
    ) {
    }
}
