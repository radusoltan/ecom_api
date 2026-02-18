<?php

declare(strict_types=1);

namespace App\Payment\Application\Query\GetPaymentHistory;

use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for GetPaymentHistoryQuery.
 *
 * Retrieves all payments for an order with pagination support.
 * Returns GetPaymentHistoryResult with paginated data and metadata.
 *
 * Note: This implementation uses in-memory pagination on the full result set
 * from findAllByOrderId(). For better performance with large result sets,
 * consider adding pagination support directly to the repository method.
 */
#[AsMessageHandler]
final readonly class GetPaymentHistoryHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
    ) {
    }

    public function __invoke(GetPaymentHistoryQuery $query): GetPaymentHistoryResult
    {
        // Get all payments for the order
        $allPayments = $this->paymentRepository->findAllByOrderId($query->orderId);
        $totalCount = count($allPayments);

        // Calculate pagination
        $offset = GetPaymentHistoryResult::calculateOffset($query->page, $query->limit);
        $totalPages = GetPaymentHistoryResult::calculateTotalPages($totalCount, $query->limit);

        // Slice the array for pagination
        $paginatedPayments = array_slice($allPayments, $offset, $query->limit);

        return new GetPaymentHistoryResult(
            payments: $paginatedPayments,
            totalCount: $totalCount,
            currentPage: $query->page,
            pageSize: $query->limit,
            totalPages: $totalPages
        );
    }
}
