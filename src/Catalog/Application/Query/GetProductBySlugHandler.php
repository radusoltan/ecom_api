<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetProductBySlugHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(GetProductBySlug $query): Product
    {
        $product = $this->productRepository->findBySlug($query->tenantId, $query->slug);

        if (null === $product) {
            throw ProductNotFoundException::withSlug($query->slug);
        }

        return $product;
    }
}
