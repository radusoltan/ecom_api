<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Fulfillment Cannot Be Processed Domain Event.
 *
 * Emitted when no warehouse is available to fulfill an order.
 * This signals that manual intervention is required.
 */
final readonly class FulfillmentCannotBeProcessed implements DomainEvent
{
    public function __construct(
        public OrderId $orderId,
        public TenantId $tenantId,
        public string $reason,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function eventName(): string
    {
        return 'order.fulfillment_cannot_be_processed';
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
