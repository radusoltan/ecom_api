<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

use App\Tax\Application\DTO\TaxRuleDTO;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Tax Rule By ID Query Handler.
 */
#[AsMessageHandler]
final readonly class GetTaxRuleByIdHandler
{
    public function __construct(
        private TaxRuleRepositoryInterface $taxRuleRepository
    ) {
    }

    public function __invoke(GetTaxRuleById $query): ?TaxRuleDTO
    {
        $taxRule = $this->taxRuleRepository->findById($query->id, $query->tenantId);

        if (null === $taxRule) {
            return null;
        }

        return new TaxRuleDTO(
            id: $taxRule->id()->toString(),
            tenantId: $taxRule->tenantId()->toString(),
            name: $taxRule->name(),
            countryCode: $taxRule->jurisdiction()->getCountryCode(),
            regionCode: $taxRule->jurisdiction()->getRegionCode(),
            ratePercentage: $taxRule->rate()->getPercentage(),
            isActive: $taxRule->isActive(),
            createdAt: $taxRule->createdAt()->format('c'),
            updatedAt: $taxRule->updatedAt()->format('c')
        );
    }
}
