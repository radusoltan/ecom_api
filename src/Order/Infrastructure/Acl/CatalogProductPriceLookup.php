<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Acl;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Order\Application\Port\ProductPriceLookupInterface;
use App\Shared\Domain\ValueObject\Money;

/**
 * ACL adapter that bridges Order's ProductPriceLookupInterface to Catalog's repository.
 *
 * This adapter translates between the Order and Catalog bounded contexts,
 * preventing direct domain-to-domain coupling.
 */
final readonly class CatalogProductPriceLookup implements ProductPriceLookupInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function findCurrentPrice(string $productId, ?string $tenantId = null): ?Money
    {
        $product = $this->productRepository->findById(ProductId::fromString($productId));

        return $product?->price();
    }
}
