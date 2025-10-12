<?php

declare(strict_types=1);

namespace App\Returns\Domain\Event;

use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain Event: ReturnRequestReceived
 *
 * Triggered when the returned item is received at the warehouse.
 *
 * Subscribers:
 * - Notify customer that item was received
 * - Schedule inspection
 * - Update inventory (pending inspection)
 */
final readonly class ReturnRequestReceived
{
    public function __construct(
        public ReturnRequestId $returnRequestId,
        public TenantId $tenantId,
        public string $warehouseId,
        public \DateTimeImmutable $occurredOn
    ) {
    }

    public static function create(
        ReturnRequestId $returnRequestId,
        TenantId $tenantId,
        string $warehouseId
    ): self {
        return new self(
            $returnRequestId,
            $tenantId,
            $warehouseId,
            new \DateTimeImmutable()
        );
    }
}
