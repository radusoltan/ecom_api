<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetDiscountUsage;

use App\Pricing\Application\DTO\Analytics\DateRangeFilter;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Query to get discount usage analytics.
 *
 * Returns metrics for discount/coupon usage:
 * - Most used coupons
 * - Total discount amounts
 * - Unused promotions
 */
final readonly class GetDiscountUsageQuery
{
    public function __construct(
        public TenantId $tenantId,
        public DateRangeFilter $dateRange,
        public int $topCouponsLimit = 10,
    ) {
    }
}
