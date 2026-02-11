<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetPriceHistoryByDateRange;

/**
 * Query to get price history within a date range.
 * Used for analytics and reporting.
 */
final readonly class GetPriceHistoryByDateRangeQuery
{
    public function __construct(
        public string $tenantId,
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
        public int $limit = 100,
        public int $offset = 0
    ) {
    }
}
