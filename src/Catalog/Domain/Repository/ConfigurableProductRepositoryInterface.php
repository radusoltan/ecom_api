<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Model\ConfigurableProduct;
use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Repository interface for ConfigurableProduct aggregate.
 */
interface ConfigurableProductRepositoryInterface
{
    /**
     * Save a configurable product.
     */
    public function save(ConfigurableProduct $configurableProduct): void;

    /**
     * Find a configurable product by ID.
     */
    public function findById(ConfigurableProductId $id, TenantId $tenantId): ?ConfigurableProduct;

    /**
     * Find a configurable product by product ID.
     */
    public function findByProductId(ProductId $productId, TenantId $tenantId): ?ConfigurableProduct;

    /**
     * Check if a configurable product exists.
     */
    public function exists(ConfigurableProductId $id, TenantId $tenantId): bool;

    /**
     * Delete a configurable product.
     */
    public function delete(ConfigurableProduct $configurableProduct): void;

    /**
     * Get all configurable products for a tenant.
     *
     * @return ConfigurableProduct[]
     */
    public function findAllByTenant(TenantId $tenantId, int $limit = 100, int $offset = 0): array;

    /**
     * Count configurable products for a tenant.
     */
    public function countByTenant(TenantId $tenantId): int;
}
