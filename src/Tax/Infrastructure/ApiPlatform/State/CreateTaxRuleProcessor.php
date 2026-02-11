<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Command\CreateTaxRule;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tax\Domain\ValueObject\TaxRuleId;
use App\Tax\Infrastructure\Persistence\Doctrine\Entity\TaxRuleEntity;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Create Tax Rule Processor.
 *
 * Handles POST /api/tax-rules requests.
 * Delegates to CreateTaxRule command.
 *
 * @implements ProcessorInterface<TaxRuleEntity, TaxRuleEntity>
 */
#[AsTaggedItem(priority: 10)]
final readonly class CreateTaxRuleProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TaxRuleRepositoryInterface $repository,
        private TenantContextInterface $tenantContext
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaxRuleEntity
    {
        if (!$data instanceof TaxRuleEntity) {
            throw new \InvalidArgumentException('Expected TaxRuleEntity');
        }

        // Get tenant from context (X-Tenant-ID header)
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new \DomainException('Tenant context not set');
        }

        // Validate required fields
        if (empty($data->getName()) || empty($data->getCountryCode()) || null === $data->getRatePercentage()) {
            throw new \InvalidArgumentException('Name, countryCode, and ratePercentage are required');
        }

        // Create command
        $id = TaxRuleId::generate();
        $command = new CreateTaxRule(
            id: $id,
            tenantId: $tenantId,
            name: $data->getName(),
            countryCode: $data->getCountryCode(),
            regionCode: $data->getRegionCode(),
            ratePercentage: (float) $data->getRatePercentage()
        );

        // Dispatch command
        $this->commandBus->dispatch($command);

        // Fetch and return created entity
        $taxRule = $this->repository->findById($id);
        if (null === $taxRule) {
            throw new \RuntimeException('Tax rule not found after creation');
        }

        return TaxRuleEntity::fromDomainModel($taxRule);
    }
}
