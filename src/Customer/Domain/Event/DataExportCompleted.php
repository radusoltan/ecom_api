<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain Event: Data Export Completed.
 *
 * Triggered when a data export has been successfully generated and is ready for download.
 */
final readonly class DataExportCompleted
{
    public function __construct(
        private DataExportRequestId $requestId,
        private CustomerId $customerId,
        private TenantId $tenantId,
        private string $filePath,
        private \DateTimeImmutable $expiresAt,
        private \DateTimeImmutable $completedAt
    ) {
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

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function completedAt(): \DateTimeImmutable
    {
        return $this->completedAt;
    }
}
