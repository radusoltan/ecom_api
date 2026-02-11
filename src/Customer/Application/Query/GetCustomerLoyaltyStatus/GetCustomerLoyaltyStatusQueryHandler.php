<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetCustomerLoyaltyStatus;

use App\Customer\Application\DTO\CustomerLoyaltyStatusDTO;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for retrieving customer loyalty status.
 *
 * Returns comprehensive information about customer's loyalty status including:
 * - Current points
 * - Current tier
 * - Next tier and points needed
 * - Lifetime statistics
 * - Program status
 */
#[AsMessageHandler]
final readonly class GetCustomerLoyaltyStatusQueryHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository,
    ) {
    }

    public function __invoke(GetCustomerLoyaltyStatusQuery $query): CustomerLoyaltyStatusDTO
    {
        $customerId = CustomerId::fromString($query->customerId);
        $tenantId = TenantId::fromString($query->tenantId);

        // Find customer
        $customer = $this->customerRepository->findById($customerId, $tenantId);
        if (null === $customer) {
            throw new \InvalidArgumentException(sprintf('Customer with ID "%s" not found', $query->customerId));
        }

        // Find loyalty program
        $program = $this->loyaltyProgramRepository->findByTenantId($tenantId);
        if (null === $program) {
            // No loyalty program exists - return basic status
            return new CustomerLoyaltyStatusDTO(
                customerId: $customer->id()->toString(),
                currentPoints: $customer->loyaltyPoints(),
                currentTier: null,
                nextTier: null,
                pointsToNextTier: null,
                lifetimePointsEarned: $customer->loyaltyPoints(), // Approximation
                lifetimePointsRedeemed: 0,
                currentTierDiscount: null,
                programName: null,
                programIsActive: false
            );
        }

        // Get current tier
        $currentTier = $program->getTierForPoints($customer->loyaltyPoints());

        // Get next tier
        $nextTier = null;
        $pointsToNextTier = null;

        $allTiers = $program->getTiers();
        if (!empty($allTiers)) {
            foreach ($allTiers as $tier) {
                if ($tier->threshold() > $customer->loyaltyPoints()) {
                    $nextTier = $tier;
                    $pointsToNextTier = $tier->threshold() - $customer->loyaltyPoints();

                    break;
                }
            }
        }

        return new CustomerLoyaltyStatusDTO(
            customerId: $customer->id()->toString(),
            currentPoints: $customer->loyaltyPoints(),
            currentTier: $currentTier?->name(),
            nextTier: $nextTier?->name(),
            pointsToNextTier: $pointsToNextTier,
            lifetimePointsEarned: $customer->loyaltyPoints(), // TODO: Track separately
            lifetimePointsRedeemed: 0, // TODO: Track separately
            currentTierDiscount: $currentTier?->discountPercentage(),
            programName: $program->name(),
            programIsActive: $program->isActive()
        );
    }
}
