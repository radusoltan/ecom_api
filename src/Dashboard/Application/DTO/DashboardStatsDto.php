<?php

declare(strict_types=1);

namespace App\Dashboard\Application\DTO;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Dashboard\Presentation\Api\Provider\DashboardStatsProvider;

#[ApiResource(
    shortName: 'DashboardStats',
    operations: [
        new Get(
            uriTemplate: '/dashboard/stats',
            provider: DashboardStatsProvider::class
        )
    ]
)]
final class DashboardStatsDto
{
    public function __construct(
        public readonly array $summary,
        public readonly array $orders,
        public readonly array $revenue,
        public readonly array $products,
        public readonly array $customers,
        public readonly array $recentOrders,
        public readonly array $topProducts,
        public readonly string $period,
        public readonly string $generatedAt
    ) {}
}
