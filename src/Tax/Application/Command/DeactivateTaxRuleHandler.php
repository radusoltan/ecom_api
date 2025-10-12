<?php

declare(strict_types=1);

namespace App\Tax\Application\Command;

use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Deactivate Tax Rule Command Handler
 */
#[AsMessageHandler]
final readonly class DeactivateTaxRuleHandler
{
    public function __construct(
        private TaxRuleRepositoryInterface $taxRuleRepository
    ) {
    }

    public function __invoke(DeactivateTaxRule $command): void
    {
        $taxRule = $this->taxRuleRepository->findById($command->id, $command->tenantId);

        if ($taxRule === null) {
            throw new \DomainException(
                sprintf('Tax rule "%s" not found', $command->id->toString())
            );
        }

        $taxRule->deactivate();

        $this->taxRuleRepository->save($taxRule);
    }
}
