<?php

declare(strict_types=1);

namespace App\AuditLog\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\AuditLog\Domain\Model\AuditLogEntry;
use App\AuditLog\Domain\ValueObject\ActionType;
use App\AuditLog\Domain\ValueObject\AuditLogId;
use App\AuditLog\Domain\ValueObject\ResourceType;
use App\AuditLog\Infrastructure\Persistence\Doctrine\Repository\DoctrineAuditLogRepository;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Domain\ValueObject\UserId;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: DoctrineAuditLogRepository::class)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(columns: ['tenant_id'], name: 'idx_audit_log_tenant_id')]
#[ORM\Index(columns: ['user_id'], name: 'idx_audit_log_user_id')]
#[ORM\Index(columns: ['action_type'], name: 'idx_audit_log_action_type')]
#[ORM\Index(columns: ['resource_type'], name: 'idx_audit_log_resource_type')]
#[ORM\Index(columns: ['resource_id'], name: 'idx_audit_log_resource_id')]
#[ORM\Index(columns: ['occurred_at'], name: 'idx_audit_log_occurred_at')]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['audit_log:read', 'audit_log:list']],
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_VIEWER')"
        ),
        new Get(
            normalizationContext: ['groups' => ['audit_log:read', 'audit_log:detail']],
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_VIEWER')"
        ),
    ],
    normalizationContext: ['groups' => ['audit_log:read']],
    paginationItemsPerPage: 50,
    paginationMaximumItemsPerPage: 200
)]
#[ApiFilter(SearchFilter::class, properties: [
    'userId' => 'exact',
    'actionType' => 'exact',
    'resourceType' => 'exact',
    'resourceId' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['occurredAt'])]
class AuditLogEntryEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['audit_log:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['audit_log:read'])]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    #[Groups(['audit_log:read'])]
    private ?string $userId = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Groups(['audit_log:read'])]
    private string $actionType;

    #[ORM\Column(type: 'string', length: 50)]
    #[Groups(['audit_log:read'])]
    private string $resourceType;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['audit_log:read'])]
    private string $resourceId;

    #[ORM\Column(type: 'json')]
    #[Groups(['audit_log:read', 'audit_log:detail'])]
    private array $metadata = [];

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    #[Groups(['audit_log:read', 'audit_log:detail'])]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['audit_log:read', 'audit_log:detail'])]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['audit_log:read'])]
    private \DateTimeImmutable $occurredAt;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function setTenantId(string $tenantId): self
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getActionType(): string
    {
        return $this->actionType;
    }

    public function setActionType(string $actionType): self
    {
        $this->actionType = $actionType;

        return $this;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function setResourceType(string $resourceType): self
    {
        $this->resourceType = $resourceType;

        return $this;
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }

    public function setResourceId(string $resourceId): self
    {
        $this->resourceId = $resourceId;

        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): self
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    /**
     * Convert domain model to entity.
     */
    public static function fromDomainModel(AuditLogEntry $entry): self
    {
        $entity = new self();
        $entity->setId($entry->id()->toString());
        $entity->setTenantId($entry->tenantId()->toString());
        $entity->setUserId($entry->userId()?->toString());
        $entity->setActionType($entry->actionType()->toString());
        $entity->setResourceType($entry->resourceType()->toString());
        $entity->setResourceId($entry->resourceId());
        $entity->setMetadata($entry->metadata());
        $entity->setIpAddress($entry->ipAddress());
        $entity->setUserAgent($entry->userAgent());
        $entity->setOccurredAt($entry->occurredAt());

        return $entity;
    }

    /**
     * Convert entity to domain model.
     */
    public function toDomainModel(): AuditLogEntry
    {
        return AuditLogEntry::reconstitute(
            id: AuditLogId::fromString($this->id),
            tenantId: TenantId::fromString($this->tenantId),
            userId: $this->userId ? UserId::fromString($this->userId) : null,
            actionType: ActionType::fromString($this->actionType),
            resourceType: ResourceType::fromString($this->resourceType),
            resourceId: $this->resourceId,
            metadata: $this->metadata,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            occurredAt: $this->occurredAt
        );
    }
}
