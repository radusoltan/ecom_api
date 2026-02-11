<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\RecordLoyaltyTransaction;

use App\Customer\Domain\Model\LoyaltyPointTransaction;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\Repository\LoyaltyPointTransactionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Record Loyalty Transaction Command Handler.
 *
 * Creates a loyalty point transaction and updates the customer's balance.
 */
#[AsMessageHandler]
final readonly class RecordLoyaltyTransactionCommandHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private LoyaltyPointTransactionRepositoryInterface $transactionRepository
    ) {
    }

    public function __invoke(RecordLoyaltyTransactionCommand $command): void
    {
        // 1. Load customer
        $customer = $this->customerRepository->findById($command->customerId, $command->tenantId);

        if (null === $customer) {
            throw new \DomainException(sprintf('Customer with ID "%s" not found', $command->customerId->toString()));
        }

        // 2. Calculate new balance based on transaction type
        $currentBalance = $customer->loyaltyPoints();
        $pointsChange = $command->points;

        // For debit transactions, points should be negative
        if ($command->type->isDebit() && $pointsChange > 0) {
            $pointsChange = -$pointsChange;
        }

        $newBalance = $currentBalance + $pointsChange;

        // 3. Validate that balance doesn't go negative
        if ($newBalance < 0) {
            throw new \DomainException(sprintf('Insufficient loyalty points. Current: %d, Requested: %d', $currentBalance, abs($pointsChange)));
        }

        // 4. Create transaction record
        $transaction = LoyaltyPointTransaction::create(
            customerId: $command->customerId,
            tenantId: $command->tenantId,
            type: $command->type,
            points: $pointsChange,
            balanceAfter: $newBalance,
            reason: $command->reason,
            orderId: $command->orderId
        );

        // 5. Save transaction
        $this->transactionRepository->save($transaction);

        // 6. Update customer balance
        if ($pointsChange > 0) {
            $customer->awardLoyaltyPoints($pointsChange, $command->reason);
        } else {
            $customer->redeemLoyaltyPoints(abs($pointsChange));
        }

        $this->customerRepository->save($customer);
    }
}
