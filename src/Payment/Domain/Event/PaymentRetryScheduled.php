<?php

declare(strict_types=1);

namespace App\Payment\Domain\Event;

use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain event triggered when a payment retry is scheduled.
 *
 * This event is raised when:
 * - A payment fails with a retryable error code
 * - The maximum retry attempts has not been reached
 * - The next retry time has been calculated and scheduled
 */
final readonly class PaymentRetryScheduled
{
    public function __construct(
        public PaymentId $paymentId,
        public TenantId $tenantId,
        public int $retryAttempt,
        public \DateTimeImmutable $scheduledFor,
        public string $errorCode,
        public string $orderId,
    ) {
    }
}
