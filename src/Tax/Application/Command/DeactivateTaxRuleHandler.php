<?php

declare(strict_types=1);

namespace App\Tax\Application\Command;

use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Deactivate Tax Rule Command Handler.
 */
#[AsMessageHandler]
final readonly class DeactivateTaxRuleHandler
{
    public function __construct(
        private TaxRuleRepositoryInterface $taxRuleRepository,
    ) {
    }

    public function __invoke(DeactivateTaxRule $command): void
    {
        // Retrieve tax rule
        $taxRule = $this->taxRuleRepository->findById($command->id);

        if (null === $taxRule) {
            throw new \DomainException(sprintf('Tax rule with ID %s not found', $command->id->toString()));
        }

        // Verify tenant ownership
        if (!$taxRule->tenantId()->equals($command->tenantId)) {
            throw new \DomainException('Tax rule does not belong to this tenant');
        }

        // Deactivate
        $taxRule->deactivate();

        // Persist and dispatch events
        $this->taxRuleRepository->save($taxRule);
    }
}
