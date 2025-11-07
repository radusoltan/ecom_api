<?php

declare(strict_types=1);

namespace App\Order\Application\Command;

use App\Order\Domain\ValueObject\FulfillmentId;
use App\Order\Domain\ValueObject\FulfillmentStatus;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Update Fulfillment Status Command.
 *
 * Transitions a fulfillment to a new status in the workflow.
 */
final readonly class UpdateFulfillmentStatus
{
    public function __construct(
        public FulfillmentId $fulfillmentId,
        public FulfillmentStatus $newStatus,
        public TenantId $tenantId,
        public ?string $carrier = null,
        public ?string $trackingNumber = null,
        public ?string $failureReason = null,
    ) {
    }
}
