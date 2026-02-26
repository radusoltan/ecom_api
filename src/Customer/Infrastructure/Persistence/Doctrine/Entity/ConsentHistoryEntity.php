<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\Model\ConsentHistory;
use App\Customer\Domain\ValueObject\ConsentHistoryId;
use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

/**
 * Consent History Entity (Infrastructure).
 *
 * Doctrine adapter for ConsentHistory domain model.
 * Stores immutable audit trail of GDPR consent changes.
 *
 * Table: consent_history
 * RLS: Enabled via tenant_id column for multi-tenant isolation
 */
#[ORM\Entity]
#[ORM\Table(name: 'consent_history')]
#[ORM\Index(columns: ['tenant_id'])]
#[ORM\Index(columns: ['customer_id'])]
#[ORM\Index(columns: ['consent_type'])]
#[ORM\Index(columns: ['created_at'])]
class ConsentHistoryEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id = '';

    #[ORM\Column(type: 'string', length: 36, nullable: false)]
    private string $customerId = '';

    #[ORM\Column(type: 'string', length: 36, nullable: false)]
    private string $tenantId = '';

    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    private string $consentType = '';

    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $granted = false;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: false)]
    private \DateTimeImmutable $createdAt;

    /**
     * Convert domain model to entity.
     */
    public static function fromDomainModel(ConsentHistory $history): self
    {
        $entity = new self();
        $entity->id = $history->id()->toString();
        $entity->customerId = $history->customerId()->toString();
        $entity->tenantId = $history->tenantId()->toString();
        $entity->consentType = $history->consentType()->value;
        $entity->granted = $history->granted();
        $entity->ipAddress = $history->ipAddress();
        $entity->userAgent = $history->userAgent();
        $entity->createdAt = $history->createdAt();

        return $entity;
    }

    /**
     * Convert entity to domain model.
     */
    public function toDomainModel(): ConsentHistory
    {
        return ConsentHistory::reconstituteFromPersistence(
            id: ConsentHistoryId::fromString($this->id),
            customerId: CustomerId::fromString($this->customerId),
            tenantId: TenantId::fromString($this->tenantId),
            consentType: ConsentType::from($this->consentType),
            granted: $this->granted,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            createdAt: $this->createdAt
        );
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getConsentType(): string
    {
        return $this->consentType;
    }

    public function isGranted(): bool
    {
        return $this->granted;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
