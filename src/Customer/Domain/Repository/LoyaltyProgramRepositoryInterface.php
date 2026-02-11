<?php

declare(strict_types=1);

namespace App\Customer\Domain\Repository;

use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Loyalty Program Repository Interface.
 *
 * Business Rules enforced at repository level:
 * - One loyalty program per tenant
 * - All operations are tenant-scoped
 */
interface LoyaltyProgramRepositoryInterface
{
    /**
     * Save a loyalty program (create or update).
     *
     * Dispatches domain events after successful persistence.
     */
    public function save(LoyaltyProgram $program): void;

    /**
     * Find a loyalty program by ID and tenant.
     *
     * @return LoyaltyProgram|null Returns null if not found or belongs to different tenant
     */
    public function findById(LoyaltyProgramId $id, TenantId $tenantId): ?LoyaltyProgram;

    /**
     * Find the loyalty program for a tenant.
     *
     * Since there's only one loyalty program per tenant, this is the primary lookup method.
     *
     * @return LoyaltyProgram|null Returns null if tenant has no loyalty program
     */
    public function findByTenantId(TenantId $tenantId): ?LoyaltyProgram;

    /**
     * Delete a loyalty program.
     *
     * This is a hard delete. Consider deactivation instead for business scenarios.
     */
    public function delete(LoyaltyProgramId $id): void;

    /**
     * Check if a tenant already has a loyalty program.
     *
     * Useful for enforcing the one-program-per-tenant constraint.
     */
    public function exists(TenantId $tenantId): bool;
}
