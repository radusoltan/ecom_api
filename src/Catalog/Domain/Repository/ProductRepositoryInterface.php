<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Slug;
use App\Shared\Domain\ValueObject\TenantId;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;

    public function findById(ProductId $id): ?Product;

    public function findBySKU(TenantId $tenantId, SKU $sku): ?Product;

    public function findBySlug(TenantId $tenantId, Slug $slug): ?Product;

    /**
     * @return Product[]
     */
    public function findByTenant(TenantId $tenantId, int $limit = 100, int $offset = 0): array;

    public function delete(ProductId $id): void;
}
