<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Inventory\Domain\Model\WarehouseId;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Fulfillment Started Domain Event.
 *
 * Emitted when a fulfillment process is initiated for an order.
 */
final readonly class FulfillmentStarted implements DomainEvent
{
    public function __construct(
        public FulfillmentId $fulfillmentId,
        public OrderId $orderId,
        public WarehouseId $warehouseId,
        public TenantId $tenantId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function eventName(): string
    {
        return 'order.fulfillment_started';
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
