<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\SubscriptionInterval;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * SubscriptionConfigured Domain Event.
 *
 * Emitted when a subscription configuration is created for a product.
 */
final readonly class SubscriptionConfigured implements DomainEvent
{
    public function __construct(
        private ProductId $productId,
        private TenantId $tenantId,
        private SubscriptionInterval $interval,
        private int $billingCycles,
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

    public function interval(): SubscriptionInterval
    {
        return $this->interval;
    }

    public function billingCycles(): int
    {
        return $this->billingCycles;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'catalog.subscription.configured';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId->toString(),
            'tenantId' => $this->tenantId->toString(),
            'interval' => $this->interval->value,
            'billingCycles' => $this->billingCycles,
            'isInfinite' => 0 === $this->billingCycles,
            'occurredOn' => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
