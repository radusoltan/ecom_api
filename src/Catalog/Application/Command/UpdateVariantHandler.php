<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\ConfigurableProductRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for UpdateVariant command.
 */
#[AsMessageHandler]
final readonly class UpdateVariantHandler
{
    public function __construct(
        private ConfigurableProductRepositoryInterface $configurableProductRepository,
    ) {
    }

    public function __invoke(UpdateVariant $command): void
    {
        // Find configurable product
        $configurableProduct = $this->configurableProductRepository->findByProductId(
            $command->productId,
            $command->tenantId
        );

        if (!$configurableProduct) {
            throw new \DomainException(sprintf('Configurable product not found for product %s', $command->productId->toString()));
        }

        // Find the variant
        $variant = null;
        foreach ($configurableProduct->getVariants() as $v) {
            if ($v->getId()->equals($command->variantId)) {
                $variant = $v;

                break;
            }
        }

        if (!$variant) {
            throw new \DomainException(sprintf('Variant %s not found for product %s', $command->variantId->toString(), $command->productId->toString()));
        }

        // Update variant properties
        if (null !== $command->price || null !== $command->stockQuantity || null !== $command->isActive) {
            $newPrice = $command->price ?? $variant->getPrice();

            // Build new stock object if stock properties changed
            $currentStock = $variant->getStock();
            $newStock = Stock::create(
                $command->stockQuantity ?? $currentStock->quantity(),
                $command->trackInventory ?? $currentStock->trackInventory(),
                $command->allowBackorder ?? $currentStock->allowBackorder()
            );

            $newIsActive = $command->isActive ?? $variant->isActive();

            $variant->update($newPrice, $newStock, $newIsActive);
        }

        // Update images if provided
        if (null !== $command->images) {
            $variant->updateImages($command->images);
        }

        // Save the configurable product
        $this->configurableProductRepository->save($configurableProduct);
    }
}
