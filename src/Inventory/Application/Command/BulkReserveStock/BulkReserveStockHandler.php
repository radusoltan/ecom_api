<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\BulkReserveStock;

use App\Inventory\Domain\Model\StockReservation;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Inventory\Domain\Repository\StockReservationRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Handler for Bulk Stock Reservation.
 *
 * Reserves multiple stock items in a single transaction.
 * If any item cannot be reserved, the entire operation is rolled back.
 */
final readonly class BulkReserveStockHandler
{
    public function __construct(
        private StockItemRepositoryInterface $stockItemRepository,
        private StockReservationRepositoryInterface $reservationRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BulkReserveStockCommand $command): BulkReserveStockResult
    {
        $reservedItems = [];
        $failedItems = [];

        try {
            // Process each item
            foreach ($command->items as $item) {
                $stockItem = $this->stockItemRepository->findByProductAndWarehouse(
                    $item->productId,
                    $item->warehouseId,
                    $command->tenantId
                );

                if (null === $stockItem) {
                    $failedItems[] = new BulkReserveStockResultItem(
                        productId: $item->productId->toString(),
                        warehouseId: $item->warehouseId->toString(),
                        quantity: $item->quantity->value(),
                        success: false,
                        error: 'Stock item not found',
                    );

                    continue;
                }

                try {
                    // Reserve stock
                    $stockItem->reserve($item->quantity, $command->referenceId);
                    $this->stockItemRepository->save($stockItem);

                    // Create reservation tracking
                    $reservation = StockReservation::create(
                        $command->referenceId,
                        $stockItem->id(),
                        $command->tenantId,
                        $item->quantity
                    );
                    $this->reservationRepository->save($reservation);

                    $reservedItems[] = new BulkReserveStockResultItem(
                        productId: $item->productId->toString(),
                        warehouseId: $item->warehouseId->toString(),
                        quantity: $item->quantity->value(),
                        success: true,
                        availableQuantity: $stockItem->calculateAvailable()->value(),
                    );

                    $this->logger->info('Bulk reservation: Stock reserved', [
                        'product_id' => $item->productId->toString(),
                        'warehouse_id' => $item->warehouseId->toString(),
                        'quantity' => $item->quantity->value(),
                        'reference_id' => $command->referenceId,
                    ]);
                } catch (\DomainException $e) {
                    $failedItems[] = new BulkReserveStockResultItem(
                        productId: $item->productId->toString(),
                        warehouseId: $item->warehouseId->toString(),
                        quantity: $item->quantity->value(),
                        success: false,
                        error: $e->getMessage(),
                    );

                    $this->logger->warning('Bulk reservation: Failed to reserve stock', [
                        'product_id' => $item->productId->toString(),
                        'warehouse_id' => $item->warehouseId->toString(),
                        'quantity' => $item->quantity->value(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $totalSuccess = count($reservedItems);
            $totalFailed = count($failedItems);
            $totalItems = $totalSuccess + $totalFailed;

            return new BulkReserveStockResult(
                referenceId: $command->referenceId,
                totalItems: $totalItems,
                successCount: $totalSuccess,
                failureCount: $totalFailed,
                reservedItems: $reservedItems,
                failedItems: $failedItems,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Bulk reservation: Unexpected error', [
                'reference_id' => $command->referenceId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
