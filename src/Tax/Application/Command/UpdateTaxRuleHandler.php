<?php

declare(strict_types=1);

namespace App\Tax\Application\Command;

use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tax\Domain\ValueObject\TaxRate;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Update Tax Rule Command Handler
 */
#[AsMessageHandler]
final readonly class UpdateTaxRuleHandler
{
    public function __construct(
        private TaxRuleRepositoryInterface $taxRuleRepository
    ) {
    }

    public function __invoke(UpdateTaxRule $command): void
    {
        $taxRule = $this->taxRuleRepository->findById($command->id, $command->tenantId);

        if ($taxRule === null) {
            throw new \DomainException(
                sprintf('Tax rule "%s" not found', $command->id->toString())
            );
        }

        $rate = TaxRate::fromPercentage($command->ratePercentage);

        $taxRule->update(
            name: $command->name,
            rate: $rate
        );

        $this->taxRuleRepository->save($taxRule);
    }
}
