<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\OptionValue;
use App\Catalog\Domain\Model\OptionValueId;
use App\Catalog\Domain\Repository\ConfigurableProductRepositoryInterface;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Catalog\Domain\ValueObject\OptionValueCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for DefineOptionValue command
 */
#[AsMessageHandler]
final readonly class DefineOptionValueHandler
{
    public function __construct(
        private ConfigurableProductRepositoryInterface $configurableProductRepository
    ) {}

    public function __invoke(DefineOptionValue $command): void
    {
        // Find configurable product
        $configurableProduct = $this->configurableProductRepository->findByProductId(
            $command->productId,
            $command->tenantId
        );

        if (!$configurableProduct) {
            throw new \DomainException(sprintf(
                'Configurable product not found for product %s',
                $command->productId->toString()
            ));
        }

        // Find the option
        $optionCode = OptionCode::fromString($command->optionCode);
        $option = null;

        foreach ($configurableProduct->getOptions() as $opt) {
            if ($opt->getCode()->equals($optionCode)) {
                $option = $opt;
                break;
            }
        }

        if (!$option) {
            throw new \DomainException(sprintf(
                'Option with code "%s" not found for product %s',
                $command->optionCode,
                $command->productId->toString()
            ));
        }

        // Create and add the option value
        $optionValue = OptionValue::create(
            OptionValueId::generate(),
            OptionValueCode::fromString($command->valueCode),
            LocalizedString::fromArray($command->nameTranslations),
            $command->position
        );

        $option->addValue($optionValue);

        // Save the configurable product
        $this->configurableProductRepository->save($configurableProduct);
    }
}
