<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

use App\Tax\Application\DTO\TaxRuleDto;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get All Tax Rules Query Handler.
 *
 * Returns all tax rules for a tenant.
 * This is an alias for GetTaxRulesByTenant for backward compatibility.
 */
#[AsMessageHandler]
final readonly class GetAllTaxRulesHandler
{
    public function __construct(
        private TaxRuleRepositoryInterface $taxRuleRepository,
    ) {
    }

    /**
     * @return TaxRuleDto[]
     */
    public function __invoke(GetAllTaxRules $query): array
    {
        // Use findByTenantId which returns all rules for the tenant
        $taxRules = $this->taxRuleRepository->findByTenantId($query->tenantId);

        // Map to DTOs
        return array_map(
            fn ($taxRule) => TaxRuleDto::fromDomainModel($taxRule),
            $taxRules
        );
    }
}
