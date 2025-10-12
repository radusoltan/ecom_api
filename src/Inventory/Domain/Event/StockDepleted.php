<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class StockDepleted implements DomainEvent
{
    public function __construct(
        public StockItemId $stockItemId,
        public ProductId $productId,
        public WarehouseId $warehouseId,
        public TenantId $tenantId,
        public Quantity $availableQuantity,
        public Quantity $threshold,
        public \DateTimeImmutable $occurredOn,
    ) {}

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
