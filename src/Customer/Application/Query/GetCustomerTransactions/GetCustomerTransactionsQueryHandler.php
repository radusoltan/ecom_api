<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetCustomerTransactions;

use App\Customer\Application\DTO\LoyaltyPointTransactionDTO;
use App\Customer\Domain\Repository\LoyaltyPointTransactionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Customer Transactions Query Handler.
 *
 * Returns paginated transaction history with total count.
 */
#[AsMessageHandler]
final readonly class GetCustomerTransactionsQueryHandler
{
    public function __construct(
        private LoyaltyPointTransactionRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array{transactions: array<LoyaltyPointTransactionDTO>, total: int, page: int, limit: int}
     */
    public function __invoke(GetCustomerTransactionsQuery $query): array
    {
        // Get paginated transactions
        $transactions = $this->repository->findByCustomerIdPaginated(
            $query->customerId,
            $query->tenantId,
            $query->page,
            $query->limit
        );

        // Get total count for pagination
        $total = $this->repository->countByCustomerId($query->customerId, $query->tenantId);

        // Convert to DTOs
        $transactionDTOs = array_map(
            fn ($transaction) => LoyaltyPointTransactionDTO::fromDomainModel($transaction),
            $transactions
        );

        return [
            'transactions' => $transactionDTOs,
            'total' => $total,
            'page' => $query->page,
            'limit' => $query->limit,
        ];
    }
}
