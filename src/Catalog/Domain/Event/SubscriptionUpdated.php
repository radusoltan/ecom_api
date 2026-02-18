<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\Subscription;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * SubscriptionUpdated Domain Event.
 *
 * Emitted when a subscription configuration is updated for a product.
 */
final readonly class SubscriptionUpdated implements DomainEvent
{
    public function __construct(
        private ProductId $productId,
        private TenantId $tenantId,
        private Subscription $oldSubscription,
        private Subscription $newSubscription,
        private \DateTimeImmutable $occurredOn,
    ) {
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function oldSubscription(): Subscription
    {
        return $this->oldSubscription;
    }

    public function newSubscription(): Subscription
    {
        return $this->newSubscription;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'catalog.subscription.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId->toString(),
            'tenantId' => $this->tenantId->toString(),
            'oldInterval' => $this->oldSubscription->interval()->value,
            'newInterval' => $this->newSubscription->interval()->value,
            'oldBillingCycles' => $this->oldSubscription->billingCycles(),
            'newBillingCycles' => $this->newSubscription->billingCycles(),
            'occurredOn' => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
