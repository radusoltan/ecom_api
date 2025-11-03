<?php

declare(strict_types=1);

namespace App\AuditLog\Application\Command\LogAuditEntry;

/**
 * Command to log an audit entry
 */
final readonly class LogAuditEntry
{
    public function __construct(
        public string $tenantId,
        public ?string $userId,
        public string $actionType,
        public string $resourceType,
        public string $resourceId,
        public array $metadata = [],
        public ?string $ipAddress = null,
        public ?string $userAgent = null
    ) {
    }
}
