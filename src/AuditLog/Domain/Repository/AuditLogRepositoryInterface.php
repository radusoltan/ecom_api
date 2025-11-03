<?php

declare(strict_types=1);

namespace App\AuditLog\Domain\Repository;

use App\AuditLog\Domain\Model\AuditLogEntry;
use App\AuditLog\Domain\ValueObject\ActionType;
use App\AuditLog\Domain\ValueObject\AuditLogId;
use App\AuditLog\Domain\ValueObject\ResourceType;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Domain\ValueObject\UserId;
use DateTimeImmutable;

interface AuditLogRepositoryInterface
{
    /**
     * Save a new audit log entry
     */
    public function save(AuditLogEntry $entry): void;

    /**
     * Find an audit log entry by ID
     */
    public function findById(AuditLogId $id, TenantId $tenantId): ?AuditLogEntry;

    /**
     * Find all audit log entries for a tenant with optional filters
     *
     * @param array{
     *     userId?: UserId,
     *     actionType?: ActionType,
     *     resourceType?: ResourceType,
     *     resourceId?: string,
     *     fromDate?: DateTimeImmutable,
     *     toDate?: DateTimeImmutable,
     *     limit?: int,
     *     offset?: int
     * } $filters
     * @return list<AuditLogEntry>
     */
    public function findByTenant(TenantId $tenantId, array $filters = []): array;

    /**
     * Find all audit log entries for a specific user
     *
     * @return list<AuditLogEntry>
     */
    public function findByUser(UserId $userId, TenantId $tenantId, int $limit = 100, int $offset = 0): array;

    /**
     * Find all audit log entries for a specific resource
     *
     * @return list<AuditLogEntry>
     */
    public function findByResource(
        ResourceType $resourceType,
        string $resourceId,
        TenantId $tenantId,
        int $limit = 100,
        int $offset = 0
    ): array;

    /**
     * Count total audit log entries for a tenant with optional filters
     *
     * @param array{
     *     userId?: UserId,
     *     actionType?: ActionType,
     *     resourceType?: ResourceType,
     *     resourceId?: string,
     *     fromDate?: DateTimeImmutable,
     *     toDate?: DateTimeImmutable
     * } $filters
     */
    public function countEntries(TenantId $tenantId, array $filters = []): int;
}
