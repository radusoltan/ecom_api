<?php

declare(strict_types=1);

namespace App\Customer\Application\Message;

/**
 * Async Message: Generate Data Export.
 *
 * Dispatched when a data export request is created.
 * Handled asynchronously to avoid blocking the request.
 *
 * The handler will:
 * 1. Collect customer data from multiple bounded contexts
 * 2. Generate JSON export file
 * 3. Store file securely
 * 4. Update request status to READY with download token
 */
final readonly class GenerateDataExportMessage
{
    public function __construct(
        private string $requestId,
        private string $customerId,
        private string $tenantId
    ) {
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }
}
