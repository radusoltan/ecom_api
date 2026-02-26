<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Domain\ValueObject\DeletionStatus;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'deletion_requests')]
#[ORM\Index(columns: ['tenant_id'])]
#[ORM\Index(columns: ['customer_id'])]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['scheduled_for'])]
class DeletionRequestEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id = '';

    #[ORM\Column(type: 'string', length: 36, nullable: false)]
    private string $customerId = '';

    #[ORM\Column(type: 'string', length: 36, nullable: false)]
    private string $tenantId = '';

    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private string $status = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $holdReason = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: false)]
    private \DateTimeImmutable $scheduledFor;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'string', length: 36, nullable: true, name: 'privacy_request_id')]
    private ?string $privacyRequestId = null;

    public static function fromDomainModel(DeletionRequest $request): self
    {
        $entity = new self();
        $entity->id = $request->id()->toString();
        $entity->customerId = $request->customerId()->toString();
        $entity->tenantId = $request->tenantId()->toString();
        $entity->status = $request->status()->value;
        $entity->reason = $request->reason();
        $entity->holdReason = $request->holdReason();
        $entity->scheduledFor = $request->scheduledFor();
        $entity->confirmedAt = $request->confirmedAt();
        $entity->completedAt = $request->completedAt();
        $entity->createdAt = $request->createdAt();
        $entity->privacyRequestId = $request->privacyRequestId();

        return $entity;
    }

    public function updateFromDomainModel(DeletionRequest $request): void
    {
        // Note: id, customerId, tenantId, createdAt should never change
        $this->status = $request->status()->value;
        $this->reason = $request->reason();
        $this->holdReason = $request->holdReason();
        $this->scheduledFor = $request->scheduledFor();
        $this->confirmedAt = $request->confirmedAt();
        $this->completedAt = $request->completedAt();
        $this->privacyRequestId = $request->privacyRequestId();
    }

    public function toDomainModel(): DeletionRequest
    {
        return DeletionRequest::reconstituteFromPersistence(
            DeletionRequestId::fromString($this->id),
            CustomerId::fromString($this->customerId),
            TenantId::fromString($this->tenantId),
            DeletionStatus::from($this->status),
            $this->reason,
            $this->holdReason,
            $this->scheduledFor,
            $this->confirmedAt,
            $this->completedAt,
            $this->createdAt,
            $this->privacyRequestId
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getHoldReason(): ?string
    {
        return $this->holdReason;
    }

    public function getScheduledFor(): \DateTimeImmutable
    {
        return $this->scheduledFor;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // Setters (for Doctrine)
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }

    public function setHoldReason(?string $holdReason): void
    {
        $this->holdReason = $holdReason;
    }

    public function setScheduledFor(\DateTimeImmutable $scheduledFor): void
    {
        $this->scheduledFor = $scheduledFor;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): void
    {
        $this->confirmedAt = $confirmedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): void
    {
        $this->completedAt = $completedAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getPrivacyRequestId(): ?string
    {
        return $this->privacyRequestId;
    }

    public function setPrivacyRequestId(?string $privacyRequestId): void
    {
        $this->privacyRequestId = $privacyRequestId;
    }
}
