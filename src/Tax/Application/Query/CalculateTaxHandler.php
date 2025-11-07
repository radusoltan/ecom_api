<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

use App\Tax\Domain\Service\TaxCalculationService;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Calculate Tax Query Handler.
 *
 * Returns tax calculation details.
 */
#[AsMessageHandler]
final readonly class CalculateTaxHandler
{
    public function __construct(
        private TaxCalculationService $taxCalculationService
    ) {
    }

    /**
     * @return array{
     *     taxAmount: int,
     *     taxRate: float,
     *     jurisdiction: string,
     *     taxRuleId: string|null,
     *     taxRuleName: string|null
     * }
     */
    public function __invoke(CalculateTax $query): array
    {
        $jurisdiction = null !== $query->regionCode
            ? TaxJurisdiction::fromCountryAndRegion($query->countryCode, $query->regionCode)
            : TaxJurisdiction::fromCountry($query->countryCode);

        return $this->taxCalculationService->calculateTax(
            amountInCents: $query->amountInCents,
            jurisdiction: $jurisdiction,
            tenantId: $query->tenantId
        );
    }
}
