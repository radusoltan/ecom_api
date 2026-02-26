<?php

declare(strict_types=1);

namespace App\Dashboard\Application\DTO;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Dashboard\Presentation\Api\Provider\DashboardStatsProvider;

#[ApiResource(
    shortName: 'DashboardStats',
    security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')",
    operations: [
        new Get(
            uriTemplate: '/dashboard/stats',
            provider: DashboardStatsProvider::class
        ),
    ]
)]
final class DashboardStatsDto
{
    public function __construct(
        /** @var array<string, mixed> */
        public readonly array $summary,
        /** @var array<string, mixed> */
        public readonly array $orders,
        /** @var array<string, mixed> */
        public readonly array $revenue,
        /** @var array<string, mixed> */
        public readonly array $products,
        /** @var array<string, mixed> */
        public readonly array $customers,
        /** @var list<array<string, mixed>> */
        public readonly array $recentOrders,
        /** @var list<array<string, mixed>> */
        public readonly array $topProducts,
        public readonly string $period,
        public readonly string $generatedAt,
    ) {
    }
}
