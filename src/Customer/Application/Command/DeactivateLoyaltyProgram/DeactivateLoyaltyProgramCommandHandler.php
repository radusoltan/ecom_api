<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\DeactivateLoyaltyProgram;

use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Deactivate Loyalty Program Command Handler.
 *
 * Deactivates a loyalty program to prevent participation.
 */
#[AsMessageHandler]
final readonly class DeactivateLoyaltyProgramCommandHandler
{
    public function __construct(
        private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository,
    ) {
    }

    public function __invoke(DeactivateLoyaltyProgramCommand $command): void
    {
        $programId = LoyaltyProgramId::fromString($command->programId);
        $tenantId = TenantId::fromString($command->tenantId);

        // Find loyalty program
        $program = $this->loyaltyProgramRepository->findById($programId, $tenantId);

        if (null === $program) {
            throw new \InvalidArgumentException(sprintf('Loyalty program with ID "%s" not found for tenant "%s"', $command->programId, $command->tenantId));
        }

        // Deactivate via domain model (records event)
        $program->deactivate();

        // Save (dispatches events)
        $this->loyaltyProgramRepository->save($program);
    }
}
