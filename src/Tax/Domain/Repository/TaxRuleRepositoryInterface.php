<?php

declare(strict_types=1);

namespace App\Tax\Domain\Repository;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use App\Tax\Domain\ValueObject\TaxRuleId;

/**
 * Tax Rule Repository Interface.
 *
 * Port for persisting and retrieving tax rules.
 */
interface TaxRuleRepositoryInterface
{
    public function save(TaxRule $taxRule): void;

    public function findById(TaxRuleId $id, TenantId $tenantId): ?TaxRule;

    /**
     * Find active tax rule for jurisdiction.
     */
    public function findByJurisdiction(TaxJurisdiction $jurisdiction, TenantId $tenantId): ?TaxRule;

    /**
     * @return TaxRule[]
     */
    public function findAll(TenantId $tenantId, int $limit = 100, int $offset = 0): array;

    /**
     * @return TaxRule[]
     */
    public function findActive(TenantId $tenantId): array;
}
