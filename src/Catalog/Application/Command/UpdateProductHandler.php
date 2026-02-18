<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateProductHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(UpdateProduct $command): void
    {
        $product = $this->productRepository->findById($command->id);

        if (null === $product) {
            throw ProductNotFoundException::withId($command->id);
        }

        // Business rule: Verify tenant ownership
        if (!$product->tenantId()->equals($command->tenantId)) {
            throw new \DomainException('Cannot update product from different tenant');
        }

        $product->update(
            name: $command->name,
            description: $command->description,
            shortDescription: $command->shortDescription,
            price: $command->price,
            categoryId: $command->categoryId,
            isFeatured: $command->isFeatured
        );

        $this->productRepository->save($product);
    }
}
