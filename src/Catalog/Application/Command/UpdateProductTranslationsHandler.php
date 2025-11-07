<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\ProductWithTranslations;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Cache\I18nCacheService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateProductTranslationsHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private I18nCacheService $cacheService
    ) {
    }

    public function __invoke(UpdateProductTranslations $command): void
    {
        // Find the product
        $product = $this->productRepository->findById($command->productId);

        if (null === $product) {
            throw new \DomainException(sprintf('Product with ID %s not found', $command->productId->toString()));
        }

        // Verify tenant
        if (!$product->tenantId()->equals($command->tenantId)) {
            throw new \DomainException('Product does not belong to the specified tenant');
        }

        // Convert to ProductWithTranslations if needed
        if (!$product instanceof ProductWithTranslations) {
            // This would be handled by a migration/adapter in a real implementation
            throw new \DomainException('Product does not support translations yet');
        }

        // Update translations
        $product->updateTranslations(
            $command->locale,
            $command->name,
            $command->description,
            $command->shortDescription
        );

        // Save the product (will dispatch domain events)
        $this->productRepository->save($product);

        // Invalidate cache for this product and locale
        $this->cacheService->invalidateProduct(
            $command->tenantId,
            $command->productId,
            $command->locale
        );
    }
}
