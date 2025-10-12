<?php

declare(strict_types=1);

namespace App\Payment\Domain\Event;

use App\Payment\Domain\ValueObject\PaymentId;

final readonly class PaymentCancelled
{
    public function __construct(
        public PaymentId $paymentId,
        public string $reason
    ) {
    }
}
