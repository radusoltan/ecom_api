<?php

declare(strict_types=1);

namespace App\Returns\Domain\Event;

use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain Event: ReturnRequestCreated
 *
 * Triggered when a customer creates a return request (RMA).
 *
 * Subscribers:
 * - Send confirmation email to customer
 * - Notify customer service team
 * - Create RMA label
 */
final readonly class ReturnRequestCreated
{
    public function __construct(
        public ReturnRequestId $returnRequestId,
        public TenantId $tenantId,
        public string $orderId,
        public string $reason,
        public \DateTimeImmutable $occurredOn
    ) {
    }

    public static function create(
        ReturnRequestId $returnRequestId,
        TenantId $tenantId,
        string $orderId,
        string $reason
    ): self {
        return new self(
            $returnRequestId,
            $tenantId,
            $orderId,
            $reason,
            new \DateTimeImmutable()
        );
    }
}
