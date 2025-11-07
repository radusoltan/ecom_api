<?php

declare(strict_types=1);

namespace App\Returns\Domain\Event;

use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain Event: ReturnRequestRejected.
 *
 * Triggered when a return request is rejected.
 *
 * Subscribers:
 * - Send rejection email with reason
 * - Return item to customer if already received
 * - Close return ticket
 */
final readonly class ReturnRequestRejected
{
    public function __construct(
        public ReturnRequestId $returnRequestId,
        public TenantId $tenantId,
        public string $rejectionReason,
        public \DateTimeImmutable $occurredOn
    ) {
    }

    public static function create(
        ReturnRequestId $returnRequestId,
        TenantId $tenantId,
        string $rejectionReason
    ): self {
        return new self(
            $returnRequestId,
            $tenantId,
            $rejectionReason,
            new \DateTimeImmutable()
        );
    }
}
