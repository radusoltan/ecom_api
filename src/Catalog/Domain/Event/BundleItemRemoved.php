<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * BundleItemRemoved Domain Event.
 *
 * Emitted when an item is removed from a bundle.
 */
final readonly class BundleItemRemoved implements DomainEvent
{
    public function __construct(
        private ProductId $bundleProductId,
        private TenantId $tenantId,
        private ProductId $removedProductId,
        private \DateTimeImmutable $occurredOn,
    ) {
    }

    public function bundleProductId(): ProductId
    {
        return $this->bundleProductId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function removedProductId(): ProductId
    {
        return $this->removedProductId;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'catalog.bundle.item_removed';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'bundleProductId' => $this->bundleProductId->toString(),
            'tenantId' => $this->tenantId->toString(),
            'removedProductId' => $this->removedProductId->toString(),
            'occurredOn' => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
