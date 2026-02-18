<?php

declare(strict_types=1);

namespace App\Payment\Application\Query\GetTransactionsByPayment;

use App\Payment\Domain\Model\Transaction;
use App\Payment\Domain\Repository\TransactionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for GetTransactionsByPaymentQuery.
 *
 * Retrieves the complete transaction audit trail for a payment.
 * Transactions are returned in chronological order (oldest first).
 *
 * @return Transaction[] Array of transaction entities ordered by creation date ascending
 */
#[AsMessageHandler]
final readonly class GetTransactionsByPaymentHandler
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
    ) {
    }

    /**
     * @return Transaction[]
     */
    public function __invoke(GetTransactionsByPaymentQuery $query): array
    {
        return $this->transactionRepository->findByPaymentId($query->paymentId);
    }
}
