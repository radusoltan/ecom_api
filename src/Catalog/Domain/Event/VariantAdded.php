<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\VariantId;
use App\Catalog\Domain\ValueObject\VariantSKU;
use App\Shared\Domain\Event\DomainEvent;

/**
 * Domain event: Variant was added to a configurable product.
 */
final readonly class VariantAdded implements DomainEvent
{
    /**
     * @param array<string, string> $optionValueMap
     */
    public function __construct(
        private ConfigurableProductId $configurableProductId,
        private ProductId $productId,
        private VariantId $variantId,
        private VariantSKU $sku,
        private array $optionValueMap,
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

    public function getVariantId(): VariantId
    {
        return $this->variantId;
    }

    public function getSku(): VariantSKU
    {
        return $this->sku;
    }

    /**
     * @return array<string, string>
     */
    public function getOptionValueMap(): array
    {
        return $this->optionValueMap;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function getEventName(): string
    {
        return 'catalog.variant.added';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'configurable_product_id' => $this->configurableProductId->toString(),
            'product_id' => $this->productId->toString(),
            'variant_id' => $this->variantId->toString(),
            'sku' => $this->sku->toString(),
            'option_value_map' => $this->optionValueMap,
            'occurred_on' => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
