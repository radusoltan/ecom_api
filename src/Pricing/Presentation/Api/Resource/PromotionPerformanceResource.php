<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Pricing\Presentation\Api\Provider\PromotionPerformanceProvider;

#[ApiResource(
    shortName: 'PromotionPerformance',
    security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')",
    operations: [
        new GetCollection(
            uriTemplate: '/analytics/pricing/promotions',
            provider: PromotionPerformanceProvider::class,
            description: 'Get promotion performance analytics - returns metrics for promotion effectiveness including revenue, orders, and conversion rate. Query params: period, start_date, end_date, limit'
        ),
    ],
    paginationEnabled: false
)]
final class PromotionPerformanceResource
{
    public function __construct(
        public string $promotionId,
        public string $promotionName,
        public string $promotionType,
        public int $ordersCount,
        public array $totalRevenue,
        public array $totalDiscount,
        public float $averageDiscountAmount,
        public float $conversionRate,
        public ?string $validFrom = null,
        public ?string $validTo = null,
    ) {
    }
}
