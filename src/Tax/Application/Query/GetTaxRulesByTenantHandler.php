<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

use App\Tax\Application\DTO\TaxRuleDto;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Tax Rules By Tenant Query Handler.
 *
 * Returns all tax rules for a specific tenant.
 * Results are not filtered by active status - returns all rules.
 */
#[AsMessageHandler]
final readonly class GetTaxRulesByTenantHandler
{
    public function __construct(
        private TaxRuleRepositoryInterface $taxRuleRepository
    ) {
    }

    /**
     * @return TaxRuleDto[]
     */
    public function __invoke(GetTaxRulesByTenant $query): array
    {
        $taxRules = $this->taxRuleRepository->findByTenantId($query->tenantId);

        return array_map(
            fn ($taxRule) => TaxRuleDto::fromDomainModel($taxRule),
            $taxRules
        );
    }
}
