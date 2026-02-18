<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\UpdateLoyaltyProgram;

use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Update Loyalty Program Command Handler.
 *
 * Handles updating loyalty program settings with validation.
 */
#[AsMessageHandler]
final readonly class UpdateLoyaltyProgramCommandHandler
{
    public function __construct(
        private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository,
    ) {
    }

    public function __invoke(UpdateLoyaltyProgramCommand $command): void
    {
        $programId = LoyaltyProgramId::fromString($command->programId);
        $tenantId = TenantId::fromString($command->tenantId);

        // Find loyalty program
        $program = $this->loyaltyProgramRepository->findById($programId, $tenantId);

        if (null === $program) {
            throw new \InvalidArgumentException(sprintf('Loyalty program with ID "%s" not found for tenant "%s"', $command->programId, $command->tenantId));
        }

        // Create value objects
        $earningRate = EarningRate::fromFloat($command->earningRate);
        $minOrderValue = Money::of((string) $command->minOrderValue, $command->minOrderCurrency);

        // Update via domain model (records events)
        $program->update(
            name: $command->name,
            description: $command->description,
            earningRate: $earningRate,
            minOrderValue: $minOrderValue,
            validityDays: $command->validityDays
        );

        // Save (dispatches events)
        $this->loyaltyProgramRepository->save($program);
    }
}
