<?php

declare(strict_types=1);

namespace App\Tax\Application\Command;

use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use App\Tax\Domain\ValueObject\TaxRate;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Create Tax Rule Command Handler.
 */
#[AsMessageHandler]
final readonly class CreateTaxRuleHandler
{
    public function __construct(
        private TaxRuleRepositoryInterface $taxRuleRepository
    ) {
    }

    public function __invoke(CreateTaxRule $command): void
    {
        // Create jurisdiction
        $jurisdiction = null !== $command->regionCode
            ? TaxJurisdiction::fromCountryAndRegion($command->countryCode, $command->regionCode)
            : TaxJurisdiction::fromCountry($command->countryCode);

        // Create tax rate
        $rate = TaxRate::fromPercentage($command->ratePercentage);

        // Create tax rule
        $taxRule = TaxRule::create(
            id: $command->id,
            tenantId: $command->tenantId,
            name: $command->name,
            jurisdiction: $jurisdiction,
            rate: $rate
        );

        // Save
        $this->taxRuleRepository->save($taxRule);
    }
}
