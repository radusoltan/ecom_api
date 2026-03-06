<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Event\VariantsGenerated;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Model\Variant;
use App\Catalog\Domain\Model\VariantId;
use App\Catalog\Domain\Repository\ConfigurableProductRepositoryInterface;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Domain\Service\VariantCombinator;
use App\Catalog\Domain\ValueObject\OptionValueCode;
use App\Catalog\Domain\ValueObject\VariantSKU;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handler for GenerateVariants command
 * Generates all possible variant combinations based on defined options.
 */
#[AsMessageHandler]
final readonly class GenerateVariantsHandler
{
    public function __construct(
        private ConfigurableProductRepositoryInterface $configurableProductRepository,
        private ProductRepositoryInterface $productRepository,
        private VariantCombinator $variantCombinator,
        private MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(GenerateVariants $command): void
    {
        // Find configurable product
        $configurableProduct = $this->configurableProductRepository->findByProductId(
            $command->productId,
            $command->tenantId
        );

        if (!$configurableProduct) {
            throw new \DomainException(sprintf('Configurable product not found for product %s', $command->productId->toString()));
        }

        // Get the base product for SKU generation
        $baseProduct = $this->productRepository->findByIdAndTenant(
            $command->productId,
            $command->tenantId
        );

        if (!$baseProduct) {
            throw new \DomainException(sprintf('Base product %s not found', $command->productId->toString()));
        }

        // Get base SKU
        $baseSku = $command->skuBase ?: $baseProduct->sku()->value();

        // Get default price (use base product price if not specified)
        $defaultPrice = $command->defaultPrice ?: $baseProduct->price();

        // Generate all combinations
        $combinations = $this->variantCombinator->generateCombinations(
            $configurableProduct->getOptions()
        );

        $generatedCount = 0;
        $skippedCount = 0;

        foreach ($combinations as $combination) {
            // Check if variant with this combination already exists
            $existingVariant = $configurableProduct->findVariantByCombination($combination);

            if ($existingVariant) {
                ++$skippedCount;

                continue; // Skip existing variants
            }

            // Generate SKU suffix from value codes
            $valueCodes = [];
            foreach ($combination as $optionCode => $valueCode) {
                $valueCodes[] = OptionValueCode::fromString($valueCode);
            }

            // Create variant SKU
            $variantSku = VariantSKU::generate($baseSku, $valueCodes);

            // Create the variant
            $variant = Variant::create(
                VariantId::generate(),
                $variantSku,
                $combination,
                $defaultPrice,
                Stock::create($command->defaultStock, true, false),
                $command->activateByDefault
            );

            // Add variant to configurable product
            $configurableProduct->addVariant($variant);
            ++$generatedCount;
        }

        // Save the configurable product with new variants
        $this->configurableProductRepository->save($configurableProduct);

        // Dispatch domain event
        $this->eventBus->dispatch(new VariantsGenerated(
            $configurableProduct->getId(),
            $command->productId,
            $generatedCount,
        ));
    }
}
