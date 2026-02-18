<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Service\TenantContextInterface;
use App\Tax\Application\Command\DeleteTaxRule;
use App\Tax\Domain\ValueObject\TaxRuleId;
use App\Tax\Infrastructure\Persistence\Doctrine\Entity\TaxRuleEntity;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Delete Tax Rule Processor.
 *
 * Handles DELETE /api/tax-rules/{id} requests.
 * Delegates to DeleteTaxRule command.
 *
 * @implements ProcessorInterface<TaxRuleEntity, void>
 */
#[AsTaggedItem(priority: 10)]
final readonly class DeleteTaxRuleProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TenantContextInterface $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new \DomainException('Tenant context not set');
        }

        // Extract ID from URI variables
        $id = TaxRuleId::fromString($uriVariables['id'] ?? '');

        // Create command
        $command = new DeleteTaxRule(
            id: $id,
            tenantId: $tenantId
        );

        // Dispatch command
        $this->commandBus->dispatch($command);
    }
}
