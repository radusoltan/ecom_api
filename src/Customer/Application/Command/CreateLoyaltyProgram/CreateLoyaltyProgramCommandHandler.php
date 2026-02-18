<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\CreateLoyaltyProgram;

use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Create Loyalty Program Command Handler.
 *
 * Handles creation of a new loyalty program with business rule enforcement.
 */
#[AsMessageHandler]
final readonly class CreateLoyaltyProgramCommandHandler
{
    public function __construct(
        private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository,
    ) {
    }

    public function __invoke(CreateLoyaltyProgramCommand $command): string
    {
        $tenantId = TenantId::fromString($command->tenantId);

        // Business rule: One loyalty program per tenant
        if ($this->loyaltyProgramRepository->exists($tenantId)) {
            throw new \DomainException(sprintf('Tenant "%s" already has a loyalty program. Only one program per tenant is allowed.', $command->tenantId));
        }

        // Create value objects
        $earningRate = EarningRate::fromFloat($command->earningRate);
        $minOrderValue = Money::of((string) $command->minOrderValue, $command->minOrderCurrency);
        $redemptionRule = RedemptionRule::create(
            conversionRate: (float) $command->redemptionRate,
            minPointsToRedeem: 0
        );

        // Create domain model using factory method
        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: $command->name,
            earningRate: $earningRate,
            minOrderValue: $minOrderValue,
            redemptionRule: $redemptionRule
        );

        // Save via repository (dispatches events)
        $this->loyaltyProgramRepository->save($program);

        return $program->id()->toString();
    }
}
