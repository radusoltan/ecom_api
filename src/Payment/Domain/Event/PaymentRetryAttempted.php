<?php

declare(strict_types=1);

namespace App\Payment\Domain\Event;

use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain event triggered when a payment retry is attempted.
 *
 * This event is raised when:
 * - A retry attempt is executed (payment gateway is called again)
 * - Records whether the retry was successful or failed
 * - Captures the result for monitoring and analytics
 */
final readonly class PaymentRetryAttempted
{
    public function __construct(
        public PaymentId $paymentId,
        public TenantId $tenantId,
        public int $attemptNumber,
        public bool $wasSuccessful,
        public ?string $errorCode = null,
        public ?string $errorMessage = null
    ) {
    }
}
