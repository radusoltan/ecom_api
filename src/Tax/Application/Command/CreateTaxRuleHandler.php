<?php

declare(strict_types=1);

namespace App\Tax\Application\Command;

use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Create Tax Rule Command Handler.
 *
 * Business Rules:
 * - Validates category is a valid TaxCategory enum value
 * - Creates TaxJurisdiction from country and optional region
 * - Creates TaxRate from percentage
 * - Delegates all business validation to TaxRule::create()
 */
#[AsMessageHandler]
final readonly class CreateTaxRuleHandler
{
    public function __construct(
        private TaxRuleRepositoryInterface $taxRuleRepository,
    ) {
    }

    public function __invoke(CreateTaxRule $command): void
    {
        // Parse category enum
        $category = TaxCategory::from($command->category);

        // Create jurisdiction
        $jurisdiction = null !== $command->regionCode
            ? TaxJurisdiction::fromCountryAndRegion($command->countryCode, $command->regionCode)
            : TaxJurisdiction::fromCountryCode($command->countryCode);

        // Create tax rate
        $rate = TaxRate::fromPercentage($command->ratePercentage);

        // Create tax rule (validates all business rules)
        $taxRule = TaxRule::create(
            id: $command->id,
            tenantId: $command->tenantId,
            jurisdiction: $jurisdiction,
            category: $category,
            rate: $rate,
            name: $command->name,
            description: $command->description,
            priority: $command->priority,
            isActive: true, // New rules are active by default
            validFrom: $command->validFrom,
            validTo: $command->validTo,
            isReverseCharge: $command->isReverseCharge
        );

        // Persist and dispatch events
        $this->taxRuleRepository->save($taxRule);
    }
}
