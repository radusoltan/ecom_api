<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetProductsHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @return \App\Catalog\Domain\Model\Product[]
     */
    public function __invoke(GetProducts $query): array
    {
        return $this->productRepository->findByTenant(
            $query->tenantId,
            $query->limit,
            $query->offset
        );
    }
}
