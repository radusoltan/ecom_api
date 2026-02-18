<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\ValueObject\PaymentId;

/**
 * MarkPaymentAsFailed Command.
 *
 * Marks a payment as failed when payment processing fails.
 * Used by webhook handlers and retry exhaustion scenarios.
 */
final readonly class MarkPaymentAsFailed
{
    public function __construct(
        public PaymentId $id,
        public string $errorMessage,
        public ?string $errorCode = null,
    ) {
    }
}
