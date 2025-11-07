<?php

declare(strict_types=1);

namespace App\Inventory\Application\DTO;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Inventory\Presentation\Api\Provider\InventoryStatsProvider;

#[ApiResource(
    shortName: 'InventoryStats',
    operations: [
        new Get(
            uriTemplate: '/inventory/stats',
            provider: InventoryStatsProvider::class
        ),
    ]
)]
final class InventoryStatsDto
{
    public function __construct(
        /** @var array<string, mixed> */
        public readonly array $summary,
        /** @var array<string, mixed> */
        public readonly array $warehouses,
        /** @var array<string, mixed> */
        public readonly array $stockLevels,
        /** @var array<string, mixed> */
        public readonly array $lowStockAlerts,
        /** @var array<string, mixed> */
        public readonly array $topProducts,
        /** @var array<string, mixed> */
        public readonly array $stockMovement,
        public readonly string $generatedAt
    ) {
    }
}
