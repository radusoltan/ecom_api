<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Order\Domain\ValueObject\FulfillmentId;
use App\Order\Domain\ValueObject\FulfillmentStatus;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Fulfillment Status Changed Domain Event.
 *
 * Emitted when a fulfillment transitions to a new status (picking, packing, shipping, etc.)
 */
final readonly class FulfillmentStatusChanged implements DomainEvent
{
    public function __construct(
        public FulfillmentId $fulfillmentId,
        public FulfillmentStatus $fromStatus,
        public FulfillmentStatus $toStatus,
        public TenantId $tenantId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function eventName(): string
    {
        return 'order.fulfillment_status_changed';
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
