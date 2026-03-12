<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class StockAdjusted implements DomainEvent
{
    public function __construct(
        public StockItemId $stockItemId,
        public TenantId $tenantId,
        public ProductId $productId,
        public Quantity $previousQuantity,
        public Quantity $newQuantity,
        public string $reason,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
