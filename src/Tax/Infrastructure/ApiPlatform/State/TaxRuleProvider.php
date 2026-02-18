<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Application\Service\TenantContextInterface;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tax\Domain\ValueObject\TaxRuleId;
use App\Tax\Infrastructure\Persistence\Doctrine\Entity\TaxRuleEntity;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Tax Rule Provider.
 *
 * Handles GET /api/tax-rules/{id} requests.
 * Provides single tax rule entity.
 *
 * @implements ProviderInterface<TaxRuleEntity>
 */
#[AsTaggedItem(priority: 10)]
final readonly class TaxRuleProvider implements ProviderInterface
{
    public function __construct(
        private TaxRuleRepositoryInterface $repository,
        private TenantContextInterface $tenantContext,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?TaxRuleEntity
    {
        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new \DomainException('Tenant context not set');
        }

        // Extract ID from URI variables
        $id = TaxRuleId::fromString($uriVariables['id'] ?? '');

        // Find tax rule
        $taxRule = $this->repository->findById($id);

        // Verify tenant ownership
        if (null !== $taxRule && !$taxRule->tenantId()->equals($tenantId)) {
            return null; // Return 404 instead of 403 for security
        }

        return null !== $taxRule ? TaxRuleEntity::fromDomainModel($taxRule) : null;
    }
}
