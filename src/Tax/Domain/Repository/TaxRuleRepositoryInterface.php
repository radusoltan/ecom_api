<?php

declare(strict_types=1);

namespace App\Tax\Domain\Repository;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;

/**
 * Tax Rule Repository Interface (Domain Port).
 *
 * Defines the contract for persisting and retrieving tax rules.
 * Implementation will be in Infrastructure layer (Doctrine).
 *
 * Business Rules for Repository:
 * - Rules must be filtered by tenant (multi-tenancy enforcement)
 * - Only return active rules for findApplicableRules
 * - Check validity dates (validFrom <= asOfDate <= validTo)
 * - Sort by priority descending (highest priority wins)
 * - Dispatch domain events after persistence
 */
interface TaxRuleRepositoryInterface
{
    /**
     * Persist a tax rule and dispatch domain events.
     */
    public function save(TaxRule $taxRule): void;

    /**
     * Find tax rule by ID.
     */
    public function findById(TaxRuleId $id): ?TaxRule;

    /**
     * Find all tax rules for a tenant.
     *
     * @return TaxRule[]
     */
    public function findByTenantId(TenantId $tenantId): array;

    /**
     * Find applicable rules for a jurisdiction and category.
     *
     * Returns rules sorted by priority (highest first).
     * Only returns active rules valid at the specified date.
     *
     * Business Rules:
     * - Filter by tenant (multi-tenancy)
     * - Only active rules (isActive = true)
     * - Validity check (validFrom <= asOfDate <= validTo)
     * - Sort by priority DESC
     *
     * @return TaxRule[]
     */
    public function findApplicableRules(
        TenantId $tenantId,
        TaxJurisdiction $jurisdiction,
        TaxCategory $category,
        \DateTimeImmutable $asOfDate,
    ): array;

    /**
     * Find the single most applicable rule (highest priority).
     *
     * Returns the highest priority active rule that:
     * - Matches the jurisdiction and category
     * - Is active (isActive = true)
     * - Is valid at the specified date (validFrom <= asOfDate <= validTo)
     *
     * Used by tax calculation service to determine applicable rate.
     *
     * @param TenantId           $tenantId     Tenant context
     * @param TaxJurisdiction    $jurisdiction Tax jurisdiction to match
     * @param TaxCategory        $category     Tax category to match
     * @param \DateTimeImmutable $asOfDate     Date for validity check
     *
     * @return TaxRule|null Best matching rule or null if no match
     */
    public function findBestMatchingRule(
        TenantId $tenantId,
        TaxJurisdiction $jurisdiction,
        TaxCategory $category,
        \DateTimeImmutable $asOfDate,
    ): ?TaxRule;

    /**
     * Delete a tax rule by ID.
     */
    public function delete(TaxRuleId $id): void;

    /**
     * Generate the next identity for a new tax rule.
     */
    public function nextIdentity(): TaxRuleId;
}
