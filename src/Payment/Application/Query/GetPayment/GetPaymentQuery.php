<?php

declare(strict_types=1);

namespace App\Payment\Application\Query\GetPayment;

use App\Payment\Domain\ValueObject\PaymentId;

/**
 * Query to retrieve a payment by ID.
 *
 * CQRS Read Operation - Query Pattern.
 * Returns Payment domain model or null if not found.
 */
final readonly class GetPaymentQuery
{
    public function __construct(
        public PaymentId $paymentId,
    ) {
    }
}
