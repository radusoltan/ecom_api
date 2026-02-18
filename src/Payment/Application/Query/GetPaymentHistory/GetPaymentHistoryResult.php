<?php

declare(strict_types=1);

namespace App\Payment\Application\Query\GetPaymentHistory;

use App\Payment\Domain\Model\Payment;

/**
 * Result DTO for GetPaymentHistoryQuery.
 *
 * Contains paginated payment data with metadata.
 */
final readonly class GetPaymentHistoryResult
{
    /**
     * @param Payment[] $payments    Array of payment aggregates
     * @param int       $totalCount  Total number of payments for this order (across all pages)
     * @param int       $currentPage Current page number (1-indexed)
     * @param int       $pageSize    Number of items per page
     * @param int       $totalPages  Total number of pages
     */
    public function __construct(
        public array $payments,
        public int $totalCount,
        public int $currentPage,
        public int $pageSize,
        public int $totalPages,
    ) {
    }

    /**
     * Check if there are more pages available.
     */
    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->totalPages;
    }

    /**
     * Check if there is a previous page available.
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Calculate the offset for database queries.
     */
    public static function calculateOffset(int $page, int $limit): int
    {
        return ($page - 1) * $limit;
    }

    /**
     * Calculate total pages from total count and page size.
     */
    public static function calculateTotalPages(int $totalCount, int $pageSize): int
    {
        return (int) ceil($totalCount / $pageSize);
    }
}
