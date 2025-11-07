<?php

declare(strict_types=1);

namespace App\AuditLog\Domain\Model;

use App\AuditLog\Domain\ValueObject\ActionType;
use App\AuditLog\Domain\ValueObject\AuditLogId;
use App\AuditLog\Domain\ValueObject\ResourceType;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Domain\ValueObject\UserId;

/**
 * AuditLogEntry Aggregate Root.
 *
 * Represents an immutable audit trail entry that records user actions in the system.
 *
 * Business Rules:
 * - Audit entries are immutable (no update/delete operations)
 * - All user actions must be logged with timestamp
 * - Metadata stored as JSON for flexibility
 * - Multi-tenant isolation enforced via tenant_id
 */
final class AuditLogEntry extends AggregateRoot
{
    private AuditLogId $id;
    private TenantId $tenantId;
    private ?UserId $userId; // Nullable for system-initiated actions
    private ActionType $actionType;
    private ResourceType $resourceType;
    private string $resourceId;
    /** @var array<string, mixed> */
    private array $metadata;
    private ?string $ipAddress;
    private ?string $userAgent;
    private \DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed> $metadata
     */
    private function __construct(
        AuditLogId $id,
        TenantId $tenantId,
        ?UserId $userId,
        ActionType $actionType,
        ResourceType $resourceType,
        string $resourceId,
        array $metadata,
        ?string $ipAddress,
        ?string $userAgent,
        \DateTimeImmutable $occurredAt
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->actionType = $actionType;
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;
        $this->metadata = $metadata;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->occurredAt = $occurredAt;
    }

    /**
     * Create a new audit log entry.
     *
     * @param array<string, mixed> $metadata
     */
    public static function log(
        TenantId $tenantId,
        ?UserId $userId,
        ActionType $actionType,
        ResourceType $resourceType,
        string $resourceId,
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        $id = AuditLogId::generate();
        $occurredAt = new \DateTimeImmutable();

        return new self(
            $id,
            $tenantId,
            $userId,
            $actionType,
            $resourceType,
            $resourceId,
            $metadata,
            $ipAddress,
            $userAgent,
            $occurredAt
        );
    }

    /**
     * Reconstitute from persistence.
     *
     * @param array<string, mixed> $metadata
     */
    public static function reconstitute(
        AuditLogId $id,
        TenantId $tenantId,
        ?UserId $userId,
        ActionType $actionType,
        ResourceType $resourceType,
        string $resourceId,
        array $metadata,
        ?string $ipAddress,
        ?string $userAgent,
        \DateTimeImmutable $occurredAt
    ): self {
        return new self(
            $id,
            $tenantId,
            $userId,
            $actionType,
            $resourceType,
            $resourceId,
            $metadata,
            $ipAddress,
            $userAgent,
            $occurredAt
        );
    }

    public function id(): AuditLogId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function userId(): ?UserId
    {
        return $this->userId;
    }

    public function actionType(): ActionType
    {
        return $this->actionType;
    }

    public function resourceType(): ResourceType
    {
        return $this->resourceType;
    }

    public function resourceId(): string
    {
        return $this->resourceId;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * Check if this action was performed by a specific user.
     */
    public function wasPerformedBy(UserId $userId): bool
    {
        if (null === $this->userId) {
            return false;
        }

        return $this->userId->equals($userId);
    }

    /**
     * Check if this action was performed on a specific resource.
     */
    public function affectedResource(ResourceType $resourceType, string $resourceId): bool
    {
        return $this->resourceType->equals($resourceType) && $this->resourceId === $resourceId;
    }

    /**
     * Check if this is a system-initiated action (no user).
     */
    public function isSystemAction(): bool
    {
        return null === $this->userId;
    }
}
