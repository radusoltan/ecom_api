<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Repository\ConfigurableProductRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for DeleteVariant command.
 */
#[AsMessageHandler]
final readonly class DeleteVariantHandler
{
    public function __construct(
        private ConfigurableProductRepositoryInterface $configurableProductRepository,
    ) {
    }

    public function __invoke(DeleteVariant $command): void
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

        // Remove variant from configurable product
        $configurableProduct->removeVariant($command->variantId);

        // Save the configurable product (orphan removal will delete the variant)
        $this->configurableProductRepository->save($configurableProduct);
    }
}
