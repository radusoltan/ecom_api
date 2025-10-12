<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\BulkReserveStock;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Command to reserve stock for multiple products in a single transaction
 *
 * Used for cart checkout scenarios where multiple items need to be reserved atomically.
 */
final readonly class BulkReserveStockCommand
{
    /**
     * @param array<BulkReserveStockItem> $items
     */
    public function __construct(
        public array $items,
        public string $referenceId,
        public TenantId $tenantId,
    ) {
    }
}
