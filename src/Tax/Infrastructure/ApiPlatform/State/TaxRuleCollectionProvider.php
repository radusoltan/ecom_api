<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Application\Service\TenantContextInterface;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tax\Infrastructure\Persistence\Doctrine\Entity\TaxRuleEntity;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Tax Rule Collection Provider.
 *
 * Handles GET /api/tax-rules requests.
 * Provides collection of tax rules filtered by tenant.
 *
 * @implements ProviderInterface<TaxRuleEntity[]>
 */
#[AsTaggedItem(priority: 10)]
final readonly class TaxRuleCollectionProvider implements ProviderInterface
{
    public function __construct(
        private TaxRuleRepositoryInterface $repository,
        private TenantContextInterface $tenantContext
    ) {
    }

    /**
     * @return TaxRuleEntity[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new \DomainException('Tenant context not set');
        }

        // Find all tax rules for tenant
        $taxRules = $this->repository->findByTenantId($tenantId);

        // Convert to entities
        return array_map(
            fn ($taxRule) => TaxRuleEntity::fromDomainModel($taxRule),
            $taxRules
        );
    }
}
