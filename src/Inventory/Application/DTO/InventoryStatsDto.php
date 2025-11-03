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
        )
    ]
)]
final class InventoryStatsDto
{
    public function __construct(
        public readonly array $summary,
        public readonly array $warehouses,
        public readonly array $stockLevels,
        public readonly array $lowStockAlerts,
        public readonly array $topProducts,
        public readonly array $stockMovement,
        public readonly string $generatedAt
    ) {}
}
