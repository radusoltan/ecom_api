<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Repository;

use App\Inventory\Domain\Model\Warehouse;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\ValueObject\TenantId;

interface WarehouseRepositoryInterface
{
    /**
     * Save warehouse aggregate
     */
    public function save(Warehouse $warehouse): void;

    /**
     * Find warehouse by ID
     */
    public function findById(WarehouseId $id): ?Warehouse;

    /**
     * Find warehouse by code within a tenant
     */
    public function findByCodeAndTenant(WarehouseCode $code, TenantId $tenantId): ?Warehouse;

    /**
     * Get all warehouses for a tenant
     *
     * @return Warehouse[]
     */
    public function findByTenant(TenantId $tenantId): array;

    /**
     * Get all active warehouses for a tenant ordered by priority
     *
     * @return Warehouse[]
     */
    public function findActiveByTenant(TenantId $tenantId): array;

    /**
     * Check if warehouse code exists for a tenant
     */
    public function codeExistsForTenant(WarehouseCode $code, TenantId $tenantId): bool;
}
