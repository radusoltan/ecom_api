<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\Bundle;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * BundleUpdated Domain Event.
 *
 * Emitted when a bundle configuration is updated.
 */
final readonly class BundleUpdated implements DomainEvent
{
    public function __construct(
        private ProductId $productId,
        private TenantId $tenantId,
        private Bundle $oldBundle,
        private Bundle $newBundle,
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

    public function oldBundle(): Bundle
    {
        return $this->oldBundle;
    }

    public function newBundle(): Bundle
    {
        return $this->newBundle;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'catalog.bundle.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId->toString(),
            'tenantId' => $this->tenantId->toString(),
            'oldItemCount' => $this->oldBundle->itemCount(),
            'newItemCount' => $this->newBundle->itemCount(),
            'oldDiscount' => $this->oldBundle->discountPercentage(),
            'newDiscount' => $this->newBundle->discountPercentage(),
            'occurredOn' => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
