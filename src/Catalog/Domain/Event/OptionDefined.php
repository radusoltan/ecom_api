<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Shared\Domain\Event\DomainEvent;

/**
 * Domain event: Option was defined for a configurable product.
 */
final readonly class OptionDefined implements DomainEvent
{
    public function __construct(
        private ConfigurableProductId $configurableProductId,
        private ProductId $productId,
        private OptionCode $optionCode,
        private LocalizedString $nameTranslations,
        private \DateTimeImmutable $occurredOn = new \DateTimeImmutable(),
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

    public function getOptionCode(): OptionCode
    {
        return $this->optionCode;
    }

    public function getNameTranslations(): LocalizedString
    {
        return $this->nameTranslations;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function getEventName(): string
    {
        return 'catalog.option.defined';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'configurable_product_id' => $this->configurableProductId->toString(),
            'product_id' => $this->productId->toString(),
            'option_code' => $this->optionCode->toString(),
            'name_translations' => $this->nameTranslations->toArray(),
            'occurred_on' => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
