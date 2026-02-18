<?php

declare(strict_types=1);

namespace App\Payment\Application\Query\GetTransactionsByPayment;

use App\Payment\Domain\Model\PaymentId;

/**
 * Query to retrieve transaction audit trail for a payment.
 *
 * CQRS Read Operation - Query Pattern.
 * Returns all transactions (authorize, capture, refund, void, etc.) for a payment.
 * Transactions are returned in chronological order (oldest first) as an immutable audit trail.
 */
final readonly class GetTransactionsByPaymentQuery
{
    public function __construct(
        public PaymentId $paymentId,
    ) {
    }
}
