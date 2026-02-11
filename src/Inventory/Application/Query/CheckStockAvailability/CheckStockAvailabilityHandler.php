<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\CheckStockAvailability;

use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for checking stock availability for multiple items.
 *
 * Business Rules:
 * - Aggregates stock across all warehouses for each product
 * - Available = onHand - reserved - allocated
 * - Overall available = true only if ALL items are available
 * - Returns 0 for products with no stock records
 */
#[AsMessageHandler]
final readonly class CheckStockAvailabilityHandler
{
    public function __construct(
        private StockItemRepositoryInterface $stockItemRepository,
    ) {
    }

    public function __invoke(CheckStockAvailabilityQuery $query): StockAvailabilityResult
    {
        $itemResults = [];
        $allAvailable = true;

        foreach ($query->items as $item) {
            // Find all stock items for this product across all warehouses
            $stockItems = $this->stockItemRepository->findByProduct(
                $item->productId,
                $query->tenantId
            );

            // Calculate total available quantity across all warehouses
            $totalAvailable = 0;
            foreach ($stockItems as $stockItem) {
                $available = $stockItem->calculateAvailable();
                $totalAvailable += $available->value();
            }

            // Check if requested quantity is available
            $isAvailable = $totalAvailable >= $item->quantity;
            if (!$isAvailable) {
                $allAvailable = false;
            }

            $itemResults[] = new ItemAvailabilityResult(
                productId: $item->productId->toString(),
                variantId: $item->variantId,
                requestedQuantity: $item->quantity,
                availableQuantity: $totalAvailable,
                available: $isAvailable,
            );
        }

        return new StockAvailabilityResult(
            available: $allAvailable,
            items: $itemResults,
        );
    }
}
