<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Shared\Domain\Event\DomainEvent;

/**
 * Domain event: Option was removed from a configurable product
 */
final readonly class OptionRemoved implements DomainEvent
{
    public function __construct(
        private ConfigurableProductId $configurableProductId,
        private ProductId $productId,
        private OptionCode $optionCode,
        private \DateTimeImmutable $occurredOn = new \DateTimeImmutable()
    ) {}

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

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function getEventName(): string
    {
        return 'catalog.option.removed';
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
            'occurred_on' => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
