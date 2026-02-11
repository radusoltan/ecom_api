<?php

declare(strict_types=1);

namespace App\Payment\Domain\Event;

use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain event triggered when all payment retry attempts have been exhausted.
 *
 * This event is raised when:
 * - All retry attempts have failed
 * - Maximum retry attempts limit has been reached
 * - No more retries will be scheduled
 * - Customer should be notified of final failure
 */
final readonly class PaymentRetryExhausted
{
    public function __construct(
        public PaymentId $paymentId,
        public TenantId $tenantId,
        public int $totalAttempts,
        public string $lastErrorCode,
        public string $lastErrorMessage,
        public string $orderId
    ) {
    }
}
