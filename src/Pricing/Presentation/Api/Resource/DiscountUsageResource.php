<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Pricing\Presentation\Api\Provider\DiscountUsageProvider;

#[ApiResource(
    shortName: 'DiscountUsage',
    security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')",
    operations: [
        new Get(
            uriTemplate: '/analytics/pricing/discounts',
            provider: DiscountUsageProvider::class,
            description: 'Get discount usage analytics - returns discount and coupon usage statistics including top coupons and unused promotions. Query params: period, start_date, end_date, top_limit'
        ),
    ],
    paginationEnabled: false
)]
final readonly class DiscountUsageResource
{
    public function __construct(
        public array $summary,
        public array $topCoupons,
        public array $unusedPromotions,
    ) {
    }
}
