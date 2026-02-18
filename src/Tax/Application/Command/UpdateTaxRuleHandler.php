<?php

declare(strict_types=1);

namespace App\Tax\Application\Command;

use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Update Tax Rule Command Handler.
 *
 * Updates the tax rate for an existing tax rule.
 * Note: This is a simplified version that only updates the rate.
 * For more complex updates, consider using UpdateTaxRate command instead.
 */
#[AsMessageHandler]
final readonly class UpdateTaxRuleHandler
{
    public function __construct(
        private TaxRuleRepositoryInterface $taxRuleRepository,
    ) {
    }

    public function __invoke(UpdateTaxRule $command): void
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

        // Create new rate and update
        $rate = TaxRate::fromPercentage($command->ratePercentage);
        $taxRule->updateRate($rate);

        // Persist and dispatch events
        $this->taxRuleRepository->save($taxRule);
    }
}
