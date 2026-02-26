<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Acl;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Order\Application\Port\StockAvailabilityLookupInterface;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * ACL adapter that bridges Order's StockAvailabilityLookupInterface to Inventory's repository.
 */
final readonly class InventoryStockAvailabilityLookup implements StockAvailabilityLookupInterface
{
    public function __construct(
        private StockItemRepositoryInterface $stockItemRepository,
    ) {
    }

    public function getAvailableQuantity(string $productId, string $tenantId): int
    {
        $stockItems = $this->stockItemRepository->findByProduct(
            ProductId::fromString($productId),
            TenantId::fromString($tenantId)
        );

        $total = 0;
        foreach ($stockItems as $stockItem) {
            $total += $stockItem->calculateAvailable()->value();
        }

        return $total;
    }
}
