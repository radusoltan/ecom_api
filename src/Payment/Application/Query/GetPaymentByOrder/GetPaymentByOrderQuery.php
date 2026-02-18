<?php

declare(strict_types=1);

namespace App\Payment\Application\Query\GetPaymentByOrder;

/**
 * Query to retrieve a payment by order ID.
 *
 * CQRS Read Operation - Query Pattern.
 * Returns the first payment found for the order.
 * For multiple payments (payment history), use GetPaymentHistoryQuery.
 */
final readonly class GetPaymentByOrderQuery
{
    public function __construct(
        public string $orderId,
    ) {
    }
}
