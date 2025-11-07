<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Order\Domain\Model\OrderId;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Fulfillment Failed Domain Event.
 *
 * Emitted when an order fulfillment fails (e.g., items not available, warehouse error).
 */
final readonly class FulfillmentFailed implements DomainEvent
{
    public function __construct(
        public FulfillmentId $fulfillmentId,
        public OrderId $orderId,
        public TenantId $tenantId,
        public string $reason,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function eventName(): string
    {
        return 'order.fulfillment_failed';
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
