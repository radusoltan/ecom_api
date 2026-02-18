<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Domain\ValueObject\BundleItem;
use App\Shared\Domain\ValueObject\Money;

/**
 * UpdateBundleCommandHandler.
 *
 * Handles updating existing bundle configurations.
 */
final readonly class UpdateBundleCommandHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(UpdateBundleCommand $command): void
    {
        // Load the bundle product
        $product = $this->productRepository->findById($command->bundleProductId);

        if (null === $product) {
            throw new \DomainException(sprintf('Product with ID "%s" not found', $command->bundleProductId->toString()));
        }

        // Validate that items don't contain bundle products (circular reference check)
        foreach ($command->items as $itemData) {
            $itemProductId = ProductId::fromString($itemData['productId']);
            $itemProduct = $this->productRepository->findById($itemProductId);

            if (null === $itemProduct) {
                throw new \DomainException(sprintf('Bundle item product with ID "%s" not found', $itemData['productId']));
            }

            if ($itemProduct->type()->isBundle()) {
                throw new \DomainException(sprintf('Cannot add bundle product "%s" to bundle. Circular references are not allowed.', $itemData['productId']));
            }
        }

        // Create bundle items
        $bundleItems = [];
        foreach ($command->items as $itemData) {
            $bundleItems[] = BundleItem::create(
                ProductId::fromString($itemData['productId']),
                $itemData['quantity'],
                Money::fromScalars(
                    $itemData['price']['amount'],
                    $itemData['price']['currency']
                )
            );
        }

        // Update bundle on product
        $product->updateBundle($bundleItems, $command->discountPercentage);

        // Save product
        $this->productRepository->save($product);
    }
}
