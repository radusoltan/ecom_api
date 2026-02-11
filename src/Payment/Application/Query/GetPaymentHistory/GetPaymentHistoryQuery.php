<?php

declare(strict_types=1);

namespace App\Payment\Application\Query\GetPaymentHistory;

/**
 * Query to retrieve payment history for an order with pagination.
 *
 * CQRS Read Operation - Query Pattern.
 * Returns all payments for an order (including failed attempts, partial payments, etc.).
 * Supports pagination for orders with many payment attempts.
 *
 * Business Rules:
 * - page: Must be >= 1 (default: 1)
 * - limit: Must be between 1 and 100 (default: 10)
 */
final readonly class GetPaymentHistoryQuery
{
    public function __construct(
        public string $orderId,
        public int $page = 1,
        public int $limit = 10
    ) {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be >= 1');
        }

        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Limit must be between 1 and 100');
        }
    }
}
