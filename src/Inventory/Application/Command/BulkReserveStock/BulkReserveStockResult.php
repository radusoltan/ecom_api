<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\BulkReserveStock;

/**
 * Result of bulk stock reservation operation.
 */
final readonly class BulkReserveStockResult
{
    /**
     * @param array<BulkReserveStockResultItem> $reservedItems
     * @param array<BulkReserveStockResultItem> $failedItems
     */
    public function __construct(
        public string $referenceId,
        public int $totalItems,
        public int $successCount,
        public int $failureCount,
        public array $reservedItems,
        public array $failedItems,
    ) {
    }

    public function isFullySuccessful(): bool
    {
        return 0 === $this->failureCount;
    }

    public function isPartiallySuccessful(): bool
    {
        return $this->successCount > 0 && $this->failureCount > 0;
    }

    public function isFullyFailed(): bool
    {
        return 0 === $this->successCount;
    }
}
