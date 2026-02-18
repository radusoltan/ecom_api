<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

use App\Tax\Application\DTO\TaxRuleDto;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Applicable Tax Rules Query Handler.
 *
 * Finds tax rules that apply to a specific jurisdiction, category, and date.
 * Delegates filtering and sorting to repository (business logic in domain).
 *
 * Business Rules (enforced by repository):
 * - Only returns active rules (isActive = true)
 * - Filters by validity date (validFrom <= asOfDate <= validTo)
 * - Sorted by priority descending (highest priority first)
 */
#[AsMessageHandler]
final readonly class GetApplicableTaxRulesHandler
{
    public function __construct(
        private TaxRuleRepositoryInterface $taxRuleRepository,
    ) {
    }

    /**
     * @return TaxRuleDto[]
     */
    public function __invoke(GetApplicableTaxRules $query): array
    {
        // Parse category enum
        $category = TaxCategory::from($query->category);

        // Create jurisdiction
        $jurisdiction = null !== $query->regionCode
            ? TaxJurisdiction::fromCountryAndRegion($query->countryCode, $query->regionCode)
            : TaxJurisdiction::fromCountryCode($query->countryCode);

        // Find applicable rules via repository
        $taxRules = $this->taxRuleRepository->findApplicableRules(
            tenantId: $query->tenantId,
            jurisdiction: $jurisdiction,
            category: $category,
            asOfDate: $query->asOfDate
        );

        // Map to DTOs
        return array_map(
            fn ($taxRule) => TaxRuleDto::fromDomainModel($taxRule),
            $taxRules
        );
    }
}
