<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetProductByIdHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(GetProductById $query): Product
    {
        $product = $this->productRepository->findById($query->id);

        if (null === $product) {
            throw ProductNotFoundException::withId($query->id);
        }

        // Verify tenant ownership
        if (!$product->tenantId()->equals($query->tenantId)) {
            throw ProductNotFoundException::withId($query->id);
        }

        return $product;
    }
}
