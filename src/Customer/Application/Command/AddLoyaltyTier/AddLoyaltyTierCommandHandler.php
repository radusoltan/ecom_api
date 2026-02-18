<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\AddLoyaltyTier;

use App\Customer\Domain\Model\LoyaltyTier;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Add Loyalty Tier Command Handler.
 *
 * Adds a new tier to an existing loyalty program.
 */
#[AsMessageHandler]
final readonly class AddLoyaltyTierCommandHandler
{
    public function __construct(
        private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository,
    ) {
    }

    public function __invoke(AddLoyaltyTierCommand $command): void
    {
        $programId = LoyaltyProgramId::fromString($command->programId);
        $tenantId = TenantId::fromString($command->tenantId);

        // Find loyalty program
        $program = $this->loyaltyProgramRepository->findById($programId, $tenantId);

        if (null === $program) {
            throw new \InvalidArgumentException(sprintf('Loyalty program with ID "%s" not found for tenant "%s"', $command->programId, $command->tenantId));
        }

        // Create free shipping minimum order value object if provided
        $freeShippingMinOrder = null;
        if (null !== $command->freeShippingMinOrder && null !== $command->freeShippingCurrency) {
            $freeShippingMinOrder = Money::of(
                (string) $command->freeShippingMinOrder,
                $command->freeShippingCurrency
            );
        }

        // Create tier using domain factory
        $tier = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: $command->name,
            threshold: $command->threshold,
            discountPercentage: $command->discountPercentage,
            freeShippingMinOrder: $freeShippingMinOrder,
            sortOrder: $command->sortOrder
        );

        // Add tier via domain model (validates business rules, records event)
        $program->addTier($tier);

        // Save (dispatches events)
        $this->loyaltyProgramRepository->save($program);
    }
}
