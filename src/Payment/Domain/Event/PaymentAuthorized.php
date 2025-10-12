<?php

declare(strict_types=1);

namespace App\Payment\Domain\Event;

use App\Payment\Domain\ValueObject\PaymentId;

final readonly class PaymentAuthorized
{
    public function __construct(
        public PaymentId $paymentId,
        public string $gatewayTransactionId
    ) {
    }
}
