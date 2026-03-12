<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\RedeemPoints;

use App\Customer\Application\DTO\RedeemPointsResult;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for redeeming loyalty points.
 *
 * Workflow:
 * 1. Validate customer exists and has sufficient points
 * 2. Validate loyalty program exists and is active
 * 3. Calculate discount using program's redemption rule
 * 4. Deduct points from customer
 * 5. Record redemption event
 * 6. Return result with discount amount
 */
#[AsMessageHandler]
final readonly class RedeemPointsCommandHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository,
    ) {
    }

    public function __invoke(RedeemPointsCommand $command): RedeemPointsResult
    {
        $customerId = CustomerId::fromString($command->customerId);
        $tenantId = TenantId::fromString($command->tenantId);

        // 1. Validate customer
        $customer = $this->customerRepository->findById($customerId, $tenantId);
        if (null === $customer) {
            throw new \InvalidArgumentException(sprintf('Customer with ID "%s" not found', $command->customerId));
        }

        // 2. Validate loyalty program
        $program = $this->loyaltyProgramRepository->findByTenantId($tenantId);
        if (null === $program) {
            throw new \DomainException(sprintf('Loyalty program not found for tenant "%s"', $command->tenantId));
        }

        if (!$program->isActive()) {
            throw new \DomainException('Loyalty program is not active. Points cannot be redeemed at this time.');
        }

        // 3. Validate points
        if ($command->pointsToRedeem <= 0) {
            throw new \InvalidArgumentException('Points to redeem must be greater than 0');
        }

        // 4. Calculate discount (this validates against redemption rule constraints)
        try {
            $discountAmount = $program->calculateDiscountForPoints($command->pointsToRedeem);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(sprintf('Invalid redemption: %s', $e->getMessage()), previous: $e);
        }

        // 5. Redeem points from customer (this validates sufficient balance)
        try {
            $customer->redeemLoyaltyPoints($command->pointsToRedeem);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(sprintf('Cannot redeem points: %s', $e->getMessage()), previous: $e);
        }

        // 6. Note: Event will be recorded automatically by the domain model
        // The Customer aggregate would need to record this event internally
        // For now, we rely on the repository to track the state change

        // 7. Save customer (dispatches events)
        $this->customerRepository->save($customer);

        // 8. Return result
        return new RedeemPointsResult(
            pointsRedeemed: $command->pointsToRedeem,
            discountAmountMinorUnits: $discountAmount->getAmount(),
            discountCurrency: $discountAmount->getCurrency()->getCurrencyCode(),
            remainingBalance: $customer->loyaltyPoints()
        );
    }
}
