<?php

declare(strict_types=1);

namespace App\Order\Application\Command;

use App\Inventory\Domain\Model\WarehouseId;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Start Fulfillment Command
 *
 * Initiates the fulfillment process for an order at a specific warehouse.
 */
final readonly class StartFulfillment
{
    public function __construct(
        public FulfillmentId $fulfillmentId,
        public OrderId $orderId,
        public WarehouseId $warehouseId,
        public TenantId $tenantId,
    ) {
    }
}
