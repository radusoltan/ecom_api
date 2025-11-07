<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Model\Variant;
use App\Catalog\Domain\Repository\ConfigurableProductRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for CreateVariant command.
 */
#[AsMessageHandler]
final readonly class CreateVariantHandler
{
    public function __construct(
        private ConfigurableProductRepositoryInterface $configurableProductRepository
    ) {
    }

    public function __invoke(CreateVariant $command): void
    {
        // Find configurable product
        $configurableProduct = $this->configurableProductRepository->findByProductId(
            $command->productId,
            $command->tenantId
        );

        if (!$configurableProduct) {
            throw new \DomainException(sprintf('Configurable product not found for product %s', $command->productId->toString()));
        }

        // Create stock object
        $stock = Stock::create(
            $command->stockQuantity,
            $command->trackInventory,
            $command->allowBackorder
        );

        // Create variant
        $variant = Variant::create(
            id: $command->variantId,
            sku: $command->sku,
            optionValueMap: $command->optionValueMap,
            price: $command->price,
            stock: $stock,
            isActive: $command->isActive
        );

        // Add variant to configurable product
        $configurableProduct->addVariant($variant);

        // Save the configurable product (cascades to variant)
        $this->configurableProductRepository->save($configurableProduct);
    }
}
