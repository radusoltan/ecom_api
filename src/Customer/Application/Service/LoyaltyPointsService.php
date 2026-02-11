<?php

declare(strict_types=1);

namespace App\Customer\Application\Service;

use App\Customer\Application\Command\RedeemPoints\RedeemPointsCommand;
use App\Customer\Application\DTO\CustomerLoyaltyStatusDTO;
use App\Customer\Application\DTO\RedeemPointsResult;
use App\Customer\Application\Query\GetCustomerLoyaltyStatus\GetCustomerLoyaltyStatusQuery;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Loyalty Points Service - Facade for loyalty operations.
 *
 * Provides high-level methods for:
 * - Awarding points for orders
 * - Redeeming points
 * - Calculating points to earn
 * - Getting customer loyalty status
 *
 * This service coordinates between commands, queries, and domain models
 * to provide a clean interface for loyalty operations.
 */
final readonly class LoyaltyPointsService
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private MessageBusInterface $commandBus,
        private CustomerRepositoryInterface $customerRepository,
        private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository,
    ) {
    }

    /**
     * Award points to customer for an order.
     *
     * Business Rules:
     * - Order total must meet minimum order value
     * - Loyalty program must be active
     * - Points are calculated based on earning rate
     *
     * @param CustomerId $customerId Customer to award points to
     * @param TenantId   $tenantId   Tenant ID
     * @param Money      $orderTotal Order total amount
     *
     * @return int Number of points awarded
     *
     * @throws \DomainException If loyalty program is not active or not found
     */
    public function awardPointsForOrder(CustomerId $customerId, TenantId $tenantId, Money $orderTotal): int
    {
        $customer = $this->customerRepository->findById($customerId, $tenantId);
        if (null === $customer) {
            throw new \InvalidArgumentException(sprintf('Customer with ID "%s" not found', $customerId->toString()));
        }

        $program = $this->loyaltyProgramRepository->findByTenantId($tenantId);
        if (null === $program) {
            throw new \DomainException('Loyalty program not found');
        }

        if (!$program->isActive()) {
            throw new \DomainException('Loyalty program is not active');
        }

        $pointsToAward = $program->calculatePointsForOrder($orderTotal);

        if ($pointsToAward > 0) {
            $customer->awardLoyaltyPoints($pointsToAward, 'Points earned from order');
            $this->customerRepository->save($customer);
        }

        return $pointsToAward;
    }

    /**
     * Redeem points for a discount.
     *
     * @param CustomerId  $customerId Customer redeeming points
     * @param TenantId    $tenantId   Tenant ID
     * @param int         $points     Number of points to redeem
     * @param string|null $orderId    Optional order ID receiving the discount
     *
     * @return RedeemPointsResult Result with discount amount and remaining balance
     *
     * @throws \InvalidArgumentException If customer not found or insufficient points
     * @throws \DomainException          If loyalty program is not active
     */
    public function redeemPoints(CustomerId $customerId, TenantId $tenantId, int $points, ?string $orderId = null): RedeemPointsResult
    {
        $customer = $this->customerRepository->findById($customerId, $tenantId);
        if (null === $customer) {
            throw new \InvalidArgumentException(sprintf('Customer with ID "%s" not found', $customerId->toString()));
        }

        $command = new RedeemPointsCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            pointsToRedeem: $points,
            orderId: $orderId
        );

        $envelope = $this->commandBus->dispatch($command);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (null === $handledStamp) {
            throw new \RuntimeException('Command was not handled');
        }

        return $handledStamp->getResult();
    }

    /**
     * Calculate how many points would be earned for an order total.
     *
     * @param Money    $orderTotal Order total amount
     * @param TenantId $tenantId   Tenant ID
     *
     * @return int Number of points that would be earned
     */
    public function calculatePointsToEarn(Money $orderTotal, TenantId $tenantId): int
    {
        $program = $this->loyaltyProgramRepository->findByTenantId($tenantId);
        if (null === $program || !$program->isActive()) {
            return 0;
        }

        return $program->calculatePointsForOrder($orderTotal);
    }

    /**
     * Get comprehensive loyalty status for a customer.
     *
     * @param CustomerId $customerId Customer ID
     * @param TenantId   $tenantId   Tenant ID
     *
     * @return CustomerLoyaltyStatusDTO Status information
     *
     * @throws \InvalidArgumentException If customer not found
     */
    public function getCustomerStatus(CustomerId $customerId, TenantId $tenantId): CustomerLoyaltyStatusDTO
    {
        $customer = $this->customerRepository->findById($customerId, $tenantId);
        if (null === $customer) {
            throw new \InvalidArgumentException(sprintf('Customer with ID "%s" not found', $customerId->toString()));
        }

        $query = new GetCustomerLoyaltyStatusQuery(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString()
        );

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (null === $handledStamp) {
            throw new \RuntimeException('Query was not handled');
        }

        return $handledStamp->getResult();
    }
}
