<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Order\Domain\ValueObject\FulfillmentId;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Fulfillment Completed Domain Event
 *
 * Emitted when an order fulfillment is successfully completed (delivered).
 */
final readonly class FulfillmentCompleted implements DomainEvent
{
    public function __construct(
        public FulfillmentId $fulfillmentId,
        public OrderId $orderId,
        public TenantId $tenantId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function eventName(): string
    {
        return 'order.fulfillment_completed';
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
