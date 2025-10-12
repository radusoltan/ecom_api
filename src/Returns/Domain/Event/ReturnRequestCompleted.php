<?php

declare(strict_types=1);

namespace App\Returns\Domain\Event;

use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain Event: ReturnRequestCompleted
 *
 * Triggered when the return process is completed (refund issued).
 *
 * Subscribers:
 * - Send refund confirmation email
 * - Update accounting systems
 * - Close return ticket
 */
final readonly class ReturnRequestCompleted
{
    public function __construct(
        public ReturnRequestId $returnRequestId,
        public TenantId $tenantId,
        public int $refundAmount,
        public string $refundCurrency,
        public \DateTimeImmutable $occurredOn
    ) {
    }

    public static function create(
        ReturnRequestId $returnRequestId,
        TenantId $tenantId,
        int $refundAmount,
        string $refundCurrency
    ): self {
        return new self(
            $returnRequestId,
            $tenantId,
            $refundAmount,
            $refundCurrency,
            new \DateTimeImmutable()
        );
    }
}
