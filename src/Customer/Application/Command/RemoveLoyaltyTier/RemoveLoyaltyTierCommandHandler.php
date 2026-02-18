<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\RemoveLoyaltyTier;

use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Remove Loyalty Tier Command Handler.
 *
 * Removes a tier from an existing loyalty program.
 */
#[AsMessageHandler]
final readonly class RemoveLoyaltyTierCommandHandler
{
    public function __construct(
        private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository,
    ) {
    }

    public function __invoke(RemoveLoyaltyTierCommand $command): void
    {
        $programId = LoyaltyProgramId::fromString($command->programId);
        $tenantId = TenantId::fromString($command->tenantId);
        $tierId = LoyaltyTierId::fromString($command->tierId);

        // Find loyalty program
        $program = $this->loyaltyProgramRepository->findById($programId, $tenantId);

        if (null === $program) {
            throw new \InvalidArgumentException(sprintf('Loyalty program with ID "%s" not found for tenant "%s"', $command->programId, $command->tenantId));
        }

        // Remove tier via domain model (validates existence, records event)
        $program->removeTier($tierId);

        // Save (dispatches events)
        $this->loyaltyProgramRepository->save($program);
    }
}
