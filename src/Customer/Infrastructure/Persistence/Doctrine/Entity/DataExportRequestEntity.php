<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\Model\DataExportRequest;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Customer\Domain\ValueObject\ExportStatus;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

/**
 * Data Export Request Doctrine Entity.
 *
 * Infrastructure adapter for persisting data export requests.
 * Converts between domain model and database representation.
 */
#[ORM\Entity]
#[ORM\Table(name: 'data_export_requests')]
#[ORM\Index(name: 'idx_data_export_requests_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_data_export_requests_customer_id', columns: ['customer_id'])]
#[ORM\Index(name: 'idx_data_export_requests_status', columns: ['status'])]
#[ORM\Index(name: 'idx_data_export_requests_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_data_export_requests_tenant_customer', columns: ['tenant_id', 'customer_id'])]
#[ORM\Index(name: 'idx_data_export_requests_tenant_status', columns: ['tenant_id', 'status'])]
class DataExportRequestEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private string $id;

    #[ORM\Column(type: 'uuid', name: 'customer_id')]
    private string $customerId;

    #[ORM\Column(type: 'uuid', name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'string', length: 500, nullable: true, name: 'file_path')]
    private ?string $filePath = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true, name: 'download_token')]
    private ?string $downloadToken = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true, name: 'expires_at')]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true, name: 'completed_at')]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'text', nullable: true, name: 'error_message')]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'string', length: 36, nullable: true, name: 'privacy_request_id')]
    private ?string $privacyRequestId = null;

    /**
     * Convert domain model to entity.
     */
    public static function fromDomainModel(DataExportRequest $request): self
    {
        $entity = new self();
        $entity->id = $request->id()->toString();
        $entity->customerId = $request->customerId()->toString();
        $entity->tenantId = $request->tenantId()->toString();
        $entity->status = $request->status()->value;
        $entity->filePath = $request->filePath();
        $entity->downloadToken = $request->downloadToken();
        $entity->expiresAt = $request->expiresAt();
        $entity->createdAt = $request->createdAt();
        $entity->completedAt = $request->completedAt();
        $entity->errorMessage = $request->errorMessage();
        $entity->privacyRequestId = $request->privacyRequestId();

        return $entity;
    }

    /**
     * Update entity from domain model (for updates).
     */
    public function updateFromDomainModel(DataExportRequest $request): void
    {
        $this->status = $request->status()->value;
        $this->filePath = $request->filePath();
        $this->downloadToken = $request->downloadToken();
        $this->expiresAt = $request->expiresAt();
        $this->completedAt = $request->completedAt();
        $this->errorMessage = $request->errorMessage();
        $this->privacyRequestId = $request->privacyRequestId();
    }

    /**
     * Convert entity to domain model.
     */
    public function toDomainModel(): DataExportRequest
    {
        return DataExportRequest::reconstituteFromPersistence(
            id: DataExportRequestId::fromString($this->id),
            customerId: CustomerId::fromString($this->customerId),
            tenantId: TenantId::fromString($this->tenantId),
            status: ExportStatus::from($this->status),
            filePath: $this->filePath,
            downloadToken: $this->downloadToken,
            expiresAt: $this->expiresAt,
            createdAt: $this->createdAt,
            completedAt: $this->completedAt,
            errorMessage: $this->errorMessage,
            privacyRequestId: $this->privacyRequestId
        );
    }

    // Getters for Doctrine

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

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function getDownloadToken(): ?string
    {
        return $this->downloadToken;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
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
