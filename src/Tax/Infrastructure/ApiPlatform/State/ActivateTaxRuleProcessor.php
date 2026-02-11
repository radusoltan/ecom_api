<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Service\TenantContextInterface;
use App\Tax\Application\Command\ActivateTaxRule;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tax\Domain\ValueObject\TaxRuleId;
use App\Tax\Infrastructure\Persistence\Doctrine\Entity\TaxRuleEntity;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Activate Tax Rule Processor.
 *
 * Handles POST /api/tax-rules/{id}/activate requests.
 * Delegates to ActivateTaxRule command.
 *
 * @implements ProcessorInterface<TaxRuleEntity, TaxRuleEntity>
 */
#[AsTaggedItem(priority: 10)]
final readonly class ActivateTaxRuleProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TaxRuleRepositoryInterface $repository,
        private TenantContextInterface $tenantContext
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaxRuleEntity
    {
        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new \DomainException('Tenant context not set');
        }

        // Extract ID from URI variables
        $id = TaxRuleId::fromString($uriVariables['id'] ?? '');

        // Create command
        $command = new ActivateTaxRule(
            id: $id,
            tenantId: $tenantId
        );

        // Dispatch command
        $this->commandBus->dispatch($command);

        // Fetch and return updated entity
        $taxRule = $this->repository->findById($id);
        if (null === $taxRule) {
            throw new \RuntimeException('Tax rule not found after activation');
        }

        return TaxRuleEntity::fromDomainModel($taxRule);
    }
}
