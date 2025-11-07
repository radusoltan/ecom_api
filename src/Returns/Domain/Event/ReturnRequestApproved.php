<?php

declare(strict_types=1);

namespace App\Returns\Domain\Event;

use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain Event: ReturnRequestApproved.
 *
 * Triggered when customer service approves a return request.
 *
 * Subscribers:
 * - Send return label to customer
 * - Send pickup instructions email
 * - Update external systems
 */
final readonly class ReturnRequestApproved
{
    public function __construct(
        public ReturnRequestId $returnRequestId,
        public TenantId $tenantId,
        public \DateTimeImmutable $occurredOn
    ) {
    }

    public static function create(
        ReturnRequestId $returnRequestId,
        TenantId $tenantId
    ): self {
        return new self(
            $returnRequestId,
            $tenantId,
            new \DateTimeImmutable()
        );
    }
}
