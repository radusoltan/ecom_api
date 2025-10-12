<?php

declare(strict_types=1);

namespace App\Payment\Domain\Event;

use App\Payment\Domain\ValueObject\PaymentId;

final readonly class PaymentRefunded
{
    public function __construct(
        public PaymentId $paymentId,
        public int $refundedAmountInCents,
        public string $reason
    ) {
    }
}
