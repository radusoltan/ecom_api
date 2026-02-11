<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Shared\Domain\Event\DomainEvent;

final readonly class StockReleased implements DomainEvent
{
    public function __construct(
        public StockItemId $stockItemId,
        public Quantity $quantity,
        public string $referenceId,
        public string $reason,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
