<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateProductHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {}

    public function __invoke(CreateProduct $command): void
    {
        // Business rule: Check if SKU already exists
        $existing = $this->productRepository->findBySKU($command->tenantId, $command->sku);
        if ($existing !== null) {
            throw new \DomainException('Product with this SKU already exists');
        }

        $stock = Stock::create(
            $command->stockQuantity,
            $command->trackInventory,
            $command->allowBackorder
        );

        $product = Product::create(
            id: $command->id,
            tenantId: $command->tenantId,
            sku: $command->sku,
            name: $command->name,
            description: $command->description,
            shortDescription: $command->shortDescription,
            price: $command->price,
            categoryId: $command->categoryId,
            stock: $stock
        );

        $this->productRepository->save($product);
    }
}
