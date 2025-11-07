<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\Bundle;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * BundleRemoved Domain Event.
 *
 * Emitted when bundle configuration is completely removed from a product.
 */
final readonly class BundleRemoved implements DomainEvent
{
    public function __construct(
        private ProductId $productId,
        private TenantId $tenantId,
        private Bundle $removedBundle,
        private \DateTimeImmutable $occurredOn
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

    public function removedBundle(): Bundle
    {
        return $this->removedBundle;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'catalog.bundle.removed';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId->toString(),
            'tenantId' => $this->tenantId->toString(),
            'removedItemCount' => $this->removedBundle->itemCount(),
            'occurredOn' => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
