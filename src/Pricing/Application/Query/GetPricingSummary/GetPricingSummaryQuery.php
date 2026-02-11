<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetPricingSummary;

use App\Pricing\Application\DTO\Analytics\DateRangeFilter;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Query to get overall pricing analytics summary.
 *
 * Aggregates all pricing analytics into a single dashboard view.
 */
final readonly class GetPricingSummaryQuery
{
    public function __construct(
        public TenantId $tenantId,
        public DateRangeFilter $dateRange,
        public int $topPromotionsLimit = 5,
    ) {
    }
}
