<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\CalculateRedemption;

use App\Customer\Application\DTO\RedeemPointsResult;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for calculating redemption preview.
 *
 * Returns what discount would be applied without actually redeeming points.
 */
#[AsMessageHandler]
final readonly class CalculateRedemptionQueryHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository,
    ) {
    }

    public function __invoke(CalculateRedemptionQuery $query): RedeemPointsResult
    {
        $customerId = CustomerId::fromString($query->customerId);
        $tenantId = TenantId::fromString($query->tenantId);

        // Validate customer exists
        $customer = $this->customerRepository->findById($customerId, $tenantId);
        if (null === $customer) {
            throw new \InvalidArgumentException(sprintf('Customer with ID "%s" not found', $query->customerId));
        }

        // Validate loyalty program
        $program = $this->loyaltyProgramRepository->findByTenantId($tenantId);
        if (null === $program) {
            throw new \DomainException(sprintf('Loyalty program not found for tenant "%s"', $query->tenantId));
        }

        if (!$program->isActive()) {
            throw new \DomainException('Loyalty program is not active. Points cannot be redeemed at this time.');
        }

        // Validate points
        if ($query->pointsToRedeem <= 0) {
            throw new \InvalidArgumentException('Points to redeem must be greater than 0');
        }

        if ($query->pointsToRedeem > $customer->loyaltyPoints()) {
            throw new \InvalidArgumentException(sprintf('Insufficient loyalty points. Available: %d, Requested: %d', $customer->loyaltyPoints(), $query->pointsToRedeem));
        }

        // Calculate discount (validates against redemption rule)
        try {
            $discountAmount = $program->calculateDiscountForPoints($query->pointsToRedeem);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(sprintf('Invalid redemption: %s', $e->getMessage()), previous: $e);
        }

        // Calculate what remaining balance would be
        $remainingBalance = $customer->loyaltyPoints() - $query->pointsToRedeem;

        return new RedeemPointsResult(
            pointsRedeemed: $query->pointsToRedeem,
            discountAmount: $discountAmount->amount(),
            discountCurrency: $discountAmount->getCurrency()->getCurrencyCode(),
            remainingBalance: $remainingBalance
        );
    }
}
