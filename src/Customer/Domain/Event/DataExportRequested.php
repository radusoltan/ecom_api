<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain Event: Data Export Requested.
 *
 * Triggered when a customer requests a GDPR data export.
 */
final readonly class DataExportRequested implements DomainEvent
{
    public function __construct(
        private DataExportRequestId $requestId,
        private CustomerId $customerId,
        private TenantId $tenantId,
        private \DateTimeImmutable $requestedAt,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function requestId(): DataExportRequestId
    {
        return $this->requestId;
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function requestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }
}
