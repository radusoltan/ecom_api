<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Event\OptionDefined;
use App\Catalog\Domain\Model\ConfigurableProduct;
use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\Option;
use App\Catalog\Domain\Model\OptionId;
use App\Catalog\Domain\Model\OptionValue;
use App\Catalog\Domain\Model\OptionValueId;
use App\Catalog\Domain\Repository\ConfigurableProductRepositoryInterface;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Catalog\Domain\ValueObject\OptionValueCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handler for DefineOption command
 */
#[AsMessageHandler]
final readonly class DefineOptionHandler
{
    public function __construct(
        private ConfigurableProductRepositoryInterface $configurableProductRepository,
        private MessageBusInterface $eventBus
    ) {}

    public function __invoke(DefineOption $command): void
    {
        // Find or create configurable product
        $configurableProduct = $this->configurableProductRepository->findByProductId(
            $command->productId,
            $command->tenantId
        );

        if (!$configurableProduct) {
            // Create new configurable product if it doesn't exist
            $configurableProduct = ConfigurableProduct::create(
                ConfigurableProductId::generate(),
                $command->productId,
                $command->tenantId
            );
        }

        // Create the option
        $option = Option::create(
            OptionId::generate(),
            OptionCode::fromString($command->code),
            LocalizedString::fromArray($command->nameTranslations),
            $command->position
        );

        // Add option values if provided
        foreach ($command->values as $valueData) {
            $optionValue = OptionValue::create(
                OptionValueId::generate(),
                OptionValueCode::fromString($valueData['code']),
                LocalizedString::fromArray($valueData['nameTranslations']),
                $valueData['position'] ?? 0
            );

            $option->addValue($optionValue);
        }

        // Define the option on the configurable product
        $configurableProduct->defineOption($option);

        // Save the configurable product
        $this->configurableProductRepository->save($configurableProduct);

        // Dispatch domain event
        $this->eventBus->dispatch(new OptionDefined(
            $configurableProduct->getId(),
            $command->productId,
            $command->tenantId,
            $option->getId(),
            $command->code,
            $command->nameTranslations
        ));
    }
}
