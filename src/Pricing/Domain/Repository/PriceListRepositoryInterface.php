<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Repository;

use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\PriceListId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Repository interface for PriceList aggregate.
 *
 * Port for persistence operations (Hexagonal Architecture)
 */
interface PriceListRepositoryInterface
{
    /**
     * Save a price list (create or update).
     */
    public function save(PriceList $priceList): void;

    /**
     * Find a price list by ID.
     */
    public function findById(PriceListId $id): ?PriceList;

    /**
     * Find all price lists for a tenant.
     *
     * @param bool $activeOnly - Only return active price lists
     *
     * @return array<PriceList>
     */
    public function findByTenant(TenantId $tenantId, bool $activeOnly = false): array;

    /**
     * Find all currently valid price lists for a tenant (active + within date range).
     *
     * @return array<PriceList>
     */
    public function findValidForTenant(TenantId $tenantId): array;

    /**
     * Delete a price list.
     */
    public function delete(PriceList $priceList): void;
}
