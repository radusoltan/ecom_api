<?php

declare(strict_types=1);

namespace App\Customer\Domain\Model;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Customer\Domain\ValueObject\ExportStatus;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Data Export Request Entity.
 *
 * Represents a GDPR-compliant data export request for a customer.
 * Not an aggregate root - managed within Customer context.
 *
 * Business Rules:
 * - Export files are stored for 24 hours after generation
 * - Status transitions must follow valid state machine rules
 * - Download tokens are cryptographically secure random strings
 * - Rate limit: 1 export request per 24 hours per customer
 * - Files contain all customer PII (GDPR Article 15)
 * - Files are stored securely and not publicly accessible
 */
final class DataExportRequest
{
    private DataExportRequestId $id;
    private CustomerId $customerId;
    private TenantId $tenantId;
    private ExportStatus $status;
    private ?string $filePath = null;
    private ?string $downloadToken = null;
    private ?\DateTimeImmutable $expiresAt = null;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $completedAt = null;
    private ?string $errorMessage = null;
    private ?string $privacyRequestId = null;

    private function __construct()
    {
    }

    /**
     * Create a new pending data export request.
     */
    public static function create(
        CustomerId $customerId,
        TenantId $tenantId,
        ?string $privacyRequestId = null,
    ): self {
        $request = new self();
        $request->id = DataExportRequestId::generate();
        $request->customerId = $customerId;
        $request->tenantId = $tenantId;
        $request->status = ExportStatus::PENDING;
        $request->privacyRequestId = $privacyRequestId;
        $request->createdAt = new \DateTimeImmutable();

        return $request;
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstituteFromPersistence(
        DataExportRequestId $id,
        CustomerId $customerId,
        TenantId $tenantId,
        ExportStatus $status,
        ?string $filePath,
        ?string $downloadToken,
        ?\DateTimeImmutable $expiresAt,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $completedAt,
        ?string $errorMessage,
        ?string $privacyRequestId = null,
    ): self {
        $request = new self();
        $request->id = $id;
        $request->customerId = $customerId;
        $request->tenantId = $tenantId;
        $request->status = $status;
        $request->filePath = $filePath;
        $request->downloadToken = $downloadToken;
        $request->expiresAt = $expiresAt;
        $request->createdAt = $createdAt;
        $request->completedAt = $completedAt;
        $request->errorMessage = $errorMessage;
        $request->privacyRequestId = $privacyRequestId;

        return $request;
    }

    /**
     * Mark the request as processing.
     */
    public function markProcessing(): void
    {
        if (!$this->status->canTransitionTo(ExportStatus::PROCESSING)) {
            throw new \DomainException(sprintf('Cannot transition from %s to PROCESSING', $this->status->label()));
        }

        $this->status = ExportStatus::PROCESSING;
    }

    /**
     * Mark the request as ready with download details.
     */
    public function markReady(string $filePath, string $downloadToken, \DateTimeImmutable $expiresAt): void
    {
        if (!$this->status->canTransitionTo(ExportStatus::READY)) {
            throw new \DomainException(sprintf('Cannot transition from %s to READY', $this->status->label()));
        }

        if ('' === trim($filePath)) {
            throw new \InvalidArgumentException('File path cannot be empty');
        }

        if ('' === trim($downloadToken)) {
            throw new \InvalidArgumentException('Download token cannot be empty');
        }

        if ($expiresAt <= new \DateTimeImmutable()) {
            throw new \InvalidArgumentException('Expiration date must be in the future');
        }

        $this->status = ExportStatus::READY;
        $this->filePath = $filePath;
        $this->downloadToken = $downloadToken;
        $this->expiresAt = $expiresAt;
        $this->completedAt = new \DateTimeImmutable();
        $this->errorMessage = null; // Clear any previous error
    }

    /**
     * Mark the request as expired.
     */
    public function markExpired(): void
    {
        if (!$this->status->canTransitionTo(ExportStatus::EXPIRED)) {
            throw new \DomainException(sprintf('Cannot transition from %s to EXPIRED', $this->status->label()));
        }

        $this->status = ExportStatus::EXPIRED;
    }

    /**
     * Mark the request as failed with error message.
     */
    public function markFailed(string $errorMessage): void
    {
        if (!$this->status->canTransitionTo(ExportStatus::FAILED)) {
            throw new \DomainException(sprintf('Cannot transition from %s to FAILED', $this->status->label()));
        }

        if ('' === trim($errorMessage)) {
            throw new \InvalidArgumentException('Error message cannot be empty');
        }

        $this->status = ExportStatus::FAILED;
        $this->errorMessage = $errorMessage;
        $this->completedAt = new \DateTimeImmutable();
    }

    /**
     * Check if the export has expired.
     */
    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        if (ExportStatus::READY !== $this->status) {
            return false;
        }

        if (null === $this->expiresAt) {
            return false;
        }

        return $this->expiresAt <= ($now ?? new \DateTimeImmutable());
    }

    /**
     * Check if the export is downloadable.
     */
    public function isDownloadable(?\DateTimeImmutable $now = null): bool
    {
        return ExportStatus::READY === $this->status && !$this->isExpired($now);
    }

    // Getters

    public function id(): DataExportRequestId
    {
        return $this->id;
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function status(): ExportStatus
    {
        return $this->status;
    }

    public function filePath(): ?string
    {
        return $this->filePath;
    }

    public function downloadToken(): ?string
    {
        return $this->downloadToken;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function privacyRequestId(): ?string
    {
        return $this->privacyRequestId;
    }
}
