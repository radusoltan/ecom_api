<?php

declare(strict_types=1);

namespace App\AuditLog\Application\Query\GetAuditLog;

/**
 * Query to retrieve audit log entries with filters.
 */
final readonly class GetAuditLog
{
    public function __construct(
        public string $tenantId,
        public ?string $userId = null,
        public ?string $actionType = null,
        public ?string $resourceType = null,
        public ?string $resourceId = null,
        public ?string $fromDate = null,
        public ?string $toDate = null,
        public int $limit = 100,
        public int $offset = 0
    ) {
    }
}
