<?php

declare(strict_types=1);

namespace App\Tax\Application\Command;

use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Delete Tax Rule Handler.
 *
 * Deletes a tax rule by ID.
 * Verifies tenant ownership before deletion.
 */
#[AsMessageHandler]
final readonly class DeleteTaxRuleHandler
{
    public function __construct(
        private TaxRuleRepositoryInterface $repository,
    ) {
    }

    public function __invoke(DeleteTaxRule $command): void
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

        // Delete
        $this->repository->delete($command->id);
    }
}
