<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Shared\Domain\Event\DomainEvent;

final readonly class WarehouseUpdated implements DomainEvent
{
    public function __construct(
        public WarehouseId $warehouseId,
        public WarehouseName $name,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
