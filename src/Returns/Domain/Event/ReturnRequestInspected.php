<?php

declare(strict_types=1);

namespace App\Returns\Domain\Event;

use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain Event: ReturnRequestInspected.
 *
 * Triggered when the returned item has been inspected.
 *
 * Subscribers:
 * - Trigger refund process if approved
 * - Return item to inventory if resellable
 * - Notify customer of inspection result
 */
final readonly class ReturnRequestInspected
{
    public function __construct(
        public ReturnRequestId $returnRequestId,
        public TenantId $tenantId,
        public bool $isResellable,
        public string $inspectionNotes,
        public \DateTimeImmutable $occurredOn
    ) {
    }

    public static function create(
        ReturnRequestId $returnRequestId,
        TenantId $tenantId,
        bool $isResellable,
        string $inspectionNotes
    ): self {
        return new self(
            $returnRequestId,
            $tenantId,
            $isResellable,
            $inspectionNotes,
            new \DateTimeImmutable()
        );
    }
}
