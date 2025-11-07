<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Repository\ProductRepositoryInterface;

/**
 * RemoveBundleCommandHandler.
 *
 * Handles removal of bundle configuration from a product.
 */
final readonly class RemoveBundleCommandHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {
    }

    public function __invoke(RemoveBundleCommand $command): void
    {
        // Load the bundle product
        $product = $this->productRepository->findById($command->bundleProductId);

        if ($product === null) {
            throw new \DomainException(
                sprintf('Product with ID "%s" not found', $command->bundleProductId->toString())
            );
        }

        // Remove bundle
        $product->removeBundle();

        // Save product
        $this->productRepository->save($product);
    }
}
