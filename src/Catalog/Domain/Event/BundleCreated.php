<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\BundleItem;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * BundleCreated Domain Event.
 *
 * Emitted when a bundle configuration is created for a product.
 */
final readonly class BundleCreated implements DomainEvent
{
    /**
     * @param array<BundleItem> $items
     */
    public function __construct(
        private ProductId $productId,
        private TenantId $tenantId,
        private array $items,
        private float $discountPercentage,
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

    /**
     * @return array<BundleItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function discountPercentage(): float
    {
        return $this->discountPercentage;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'catalog.bundle.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId->toString(),
            'tenantId' => $this->tenantId->toString(),
            'itemCount' => count($this->items),
            'discountPercentage' => $this->discountPercentage,
            'occurredOn' => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
