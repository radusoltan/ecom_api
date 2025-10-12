<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

use App\Tax\Application\DTO\TaxRuleDTO;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get All Tax Rules Query Handler
 */
#[AsMessageHandler]
final readonly class GetAllTaxRulesHandler
{
    public function __construct(
        private TaxRuleRepositoryInterface $taxRuleRepository
    ) {
    }

    /**
     * @return TaxRuleDTO[]
     */
    public function __invoke(GetAllTaxRules $query): array
    {
        $taxRules = $query->activeOnly
            ? $this->taxRuleRepository->findActive($query->tenantId)
            : $this->taxRuleRepository->findAll($query->tenantId, $query->limit, $query->offset);

        return array_map(
            fn ($taxRule) => new TaxRuleDTO(
                id: $taxRule->id()->toString(),
                tenantId: $taxRule->tenantId()->toString(),
                name: $taxRule->name(),
                countryCode: $taxRule->jurisdiction()->getCountryCode(),
                regionCode: $taxRule->jurisdiction()->getRegionCode(),
                ratePercentage: $taxRule->rate()->getPercentage(),
                isActive: $taxRule->isActive(),
                createdAt: $taxRule->createdAt()->format('c'),
                updatedAt: $taxRule->updatedAt()->format('c')
            ),
            $taxRules
        );
    }
}
