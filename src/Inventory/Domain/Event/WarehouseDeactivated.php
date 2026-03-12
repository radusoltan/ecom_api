<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class WarehouseDeactivated implements DomainEvent
{
    public function __construct(
        public WarehouseId $warehouseId,
        public TenantId $tenantId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
