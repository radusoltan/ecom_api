<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetPromotionPerformance;

use App\Pricing\Application\DTO\Analytics\DateRangeFilter;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Query to get promotion performance analytics.
 *
 * Returns metrics for promotion effectiveness:
 * - Revenue per promotion
 * - Orders using promotion
 * - Conversion rate
 * - Average discount given
 */
final readonly class GetPromotionPerformanceQuery
{
    public function __construct(
        public TenantId $tenantId,
        public DateRangeFilter $dateRange,
        public ?string $promotionId = null,
        public int $limit = 10,
    ) {
    }
}
