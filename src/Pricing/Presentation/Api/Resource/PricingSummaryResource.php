<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Pricing\Presentation\Api\Provider\PricingSummaryProvider;

#[ApiResource(
    shortName: 'PricingSummary',
    security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')",
    operations: [
        new Get(
            uriTemplate: '/analytics/pricing/summary',
            provider: PricingSummaryProvider::class,
            description: 'Get overall pricing analytics summary - returns aggregated pricing metrics including revenue, discounts, and top promotions. Query params: period, start_date, end_date, top_promotions_limit'
        ),
    ],
    paginationEnabled: false
)]
final readonly class PricingSummaryResource
{
    public function __construct(
        public array $period,
        public array $summary,
        public array $topPromotions,
    ) {
    }
}
