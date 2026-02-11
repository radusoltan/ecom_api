<?php

declare(strict_types=1);

namespace App\Tax\Application\Command;

use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Update Tax Rate Command Handler.
 *
 * Updates the tax rate percentage for an existing active tax rule.
 * Records a TaxRateChanged domain event.
 *
 * Business Rules (enforced by TaxRule::updateRate()):
 * - Tax rule must be active
 * - Rate must be valid (>= 0, <= 100)
 * - Events are dispatched on successful update
 */
#[AsMessageHandler]
final readonly class UpdateTaxRateHandler
{
    public function __construct(
        private TaxRuleRepositoryInterface $repository
    ) {
    }

    public function __invoke(UpdateTaxRate $command): void
    {
        // Retrieve tax rule
        $taxRule = $this->repository->findById($command->id);

        if (null === $taxRule) {
            throw new \DomainException(sprintf('Tax rule with ID %s not found', $command->id->toString()));
        }

        // Verify tenant ownership
        if (!$taxRule->tenantId()->equals($command->tenantId)) {
            throw new \DomainException('Tax rule does not belong to this tenant');
        }

        // Create new rate value object
        $newRate = TaxRate::fromPercentage($command->ratePercentage);

        // Update rate (validates business rules and records event)
        $taxRule->updateRate($newRate);

        // Persist and dispatch events
        $this->repository->save($taxRule);
    }
}
