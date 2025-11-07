<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\Event\DomainEvent;

/**
 * Domain event: Variants were automatically generated for a configurable product.
 */
final readonly class VariantsGenerated implements DomainEvent
{
    public function __construct(
        private ConfigurableProductId $configurableProductId,
        private ProductId $productId,
        private int $variantsCount,
        private \DateTimeImmutable $occurredOn = new \DateTimeImmutable()
    ) {
    }

    public function getConfigurableProductId(): ConfigurableProductId
    {
        return $this->configurableProductId;
    }

    public function getProductId(): ProductId
    {
        return $this->productId;
    }

    public function getVariantsCount(): int
    {
        return $this->variantsCount;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function getEventName(): string
    {
        return 'catalog.variants.generated';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'configurable_product_id' => $this->configurableProductId->toString(),
            'product_id' => $this->productId->toString(),
            'variants_count' => $this->variantsCount,
            'occurred_on' => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
