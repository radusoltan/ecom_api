<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\ActivateLoyaltyProgram;

use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Activate Loyalty Program Command Handler.
 *
 * Activates a loyalty program allowing customers to participate.
 */
#[AsMessageHandler]
final readonly class ActivateLoyaltyProgramCommandHandler
{
    public function __construct(
        private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository,
    ) {
    }

    public function __invoke(ActivateLoyaltyProgramCommand $command): void
    {
        $programId = LoyaltyProgramId::fromString($command->programId);
        $tenantId = TenantId::fromString($command->tenantId);

        // Find loyalty program
        $program = $this->loyaltyProgramRepository->findById($programId, $tenantId);

        if (null === $program) {
            throw new \InvalidArgumentException(sprintf('Loyalty program with ID "%s" not found for tenant "%s"', $command->programId, $command->tenantId));
        }

        // Activate via domain model (records event)
        $program->activate();

        // Save (dispatches events)
        $this->loyaltyProgramRepository->save($program);
    }
}
