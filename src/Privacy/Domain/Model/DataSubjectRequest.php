<?php

declare(strict_types=1);

namespace App\Privacy\Domain\Model;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Event\DataErasureRequested;
use App\Privacy\Domain\Event\DataSubjectRequestCompleted;
use App\Privacy\Domain\Event\DataSubjectRequestSubmitted;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Data Subject Request Aggregate Root.
 *
 * Business Rules (GDPR Compliance):
 * - Requests must be processed within 30 days (Article 12)
 * - Can extend by additional 60 days for complex requests (Article 12)
 * - Must verify identity before fulfilling request
 * - Erasure requests cannot delete data required for legal/contractual obligations
 * - Portability requests must be in machine-readable format (JSON/CSV)
 * - Access requests must include all personal data categories
 */
final class DataSubjectRequest extends AggregateRoot
{
    private const STANDARD_PROCESSING_DAYS = 30;
    private const EXTENDED_PROCESSING_DAYS = 90;

    private DataSubjectRequestId $id;
    private TenantId $tenantId;
    private CustomerId $customerId;
    private RequestType $requestType;
    private RequestStatus $status;
    private ?string $reason;
    private ?string $reviewNotes;
    private ?string $rejectionReason;
    private ?array $exportData;
    private \DateTimeImmutable $submittedAt;
    private ?\DateTimeImmutable $completedAt;
    private \DateTimeImmutable $deadline;
    private bool $isExtended;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function submit(
        DataSubjectRequestId $id,
        TenantId $tenantId,
        CustomerId $customerId,
        RequestType $requestType,
        ?string $reason = null,
    ): self {
        if (null !== $reason) {
            self::validateReason($reason);
        }

        $request = new self();
        $request->id = $id;
        $request->tenantId = $tenantId;
        $request->customerId = $customerId;
        $request->requestType = $requestType;
        $request->status = RequestStatus::pending();
        $request->reason = $reason;
        $request->reviewNotes = null;
        $request->rejectionReason = null;
        $request->exportData = null;
        $request->submittedAt = new \DateTimeImmutable();
        $request->completedAt = null;
        $request->deadline = $request->submittedAt->modify(sprintf('+%d days', self::STANDARD_PROCESSING_DAYS));
        $request->isExtended = false;
        $request->createdAt = new \DateTimeImmutable();
        $request->updatedAt = new \DateTimeImmutable();

        $request->recordEvent(new DataSubjectRequestSubmitted(
            $request->id,
            $request->customerId,
            $request->requestType,
            $request->submittedAt
        ));

        // Special event for erasure requests (triggers cascading deletion workflow)
        if ($request->requestType->isDestructive()) {
            $request->recordEvent(new DataErasureRequested(
                $request->id,
                $request->customerId,
                $request->submittedAt
            ));
        }

        return $request;
    }

    public static function reconstituteFromPersistence(
        DataSubjectRequestId $id,
        TenantId $tenantId,
        CustomerId $customerId,
        RequestType $requestType,
        RequestStatus $status,
        ?string $reason,
        ?string $reviewNotes,
        ?string $rejectionReason,
        ?array $exportData,
        \DateTimeImmutable $submittedAt,
        ?\DateTimeImmutable $completedAt,
        \DateTimeImmutable $deadline,
        bool $isExtended,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $request = new self();
        $request->id = $id;
        $request->tenantId = $tenantId;
        $request->customerId = $customerId;
        $request->requestType = $requestType;
        $request->status = $status;
        $request->reason = $reason;
        $request->reviewNotes = $reviewNotes;
        $request->rejectionReason = $rejectionReason;
        $request->exportData = $exportData;
        $request->submittedAt = $submittedAt;
        $request->completedAt = $completedAt;
        $request->deadline = $deadline;
        $request->isExtended = $isExtended;
        $request->createdAt = $createdAt;
        $request->updatedAt = $updatedAt;

        return $request;
    }

    public function startReview(string $reviewNotes): void
    {
        $this->ensureCanTransition(RequestStatus::underReview());

        self::validateReviewNotes($reviewNotes);

        $this->status = RequestStatus::underReview();
        $this->reviewNotes = $reviewNotes;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function complete(?array $exportData = null): void
    {
        $this->ensureCanTransition(RequestStatus::completed());

        if ($this->requestType->equals(RequestType::access()) || $this->requestType->equals(RequestType::portability())) {
            if (null === $exportData || empty($exportData)) {
                throw new \InvalidArgumentException('Export data is required for access/portability requests');
            }
        }

        $this->status = RequestStatus::completed();
        $this->exportData = $exportData;
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new DataSubjectRequestCompleted(
            $this->id,
            $this->requestType,
            $this->completedAt
        ));
    }

    public function reject(string $rejectionReason): void
    {
        $this->ensureCanTransition(RequestStatus::rejected());

        self::validateRejectionReason($rejectionReason);

        $this->status = RequestStatus::rejected();
        $this->rejectionReason = $rejectionReason;
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function extendDeadline(): void
    {
        if ($this->isExtended) {
            throw new \InvalidArgumentException('Request deadline has already been extended once');
        }

        if ($this->status->isFinal()) {
            throw new \InvalidArgumentException('Cannot extend deadline for completed/rejected requests');
        }

        $this->deadline = $this->submittedAt->modify(sprintf('+%d days', self::EXTENDED_PROCESSING_DAYS));
        $this->isExtended = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isOverdue(): bool
    {
        return !$this->status->isFinal() && new \DateTimeImmutable() > $this->deadline;
    }

    public function daysUntilDeadline(): int
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($this->deadline);

        return $diff->invert ? -$diff->days : $diff->days;
    }

    private function ensureCanTransition(RequestStatus $newStatus): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(sprintf('Cannot transition from status "%s" to "%s"', $this->status->value(), $newStatus->value()));
        }
    }

    private static function validateReason(?string $reason): void
    {
        if (null === $reason) {
            return;
        }

        $trimmed = trim($reason);
        if ('' !== $trimmed && strlen($trimmed) < 10) {
            throw new \InvalidArgumentException('Request reason must be at least 10 characters if provided');
        }

        if (strlen($trimmed) > 1000) {
            throw new \InvalidArgumentException(sprintf('Request reason too long. Maximum 1000 characters. Got: %d', strlen($trimmed)));
        }
    }

    private static function validateReviewNotes(string $reviewNotes): void
    {
        $trimmed = trim($reviewNotes);
        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Review notes cannot be empty');
        }

        if (strlen($trimmed) > 2000) {
            throw new \InvalidArgumentException(sprintf('Review notes too long. Maximum 2000 characters. Got: %d', strlen($trimmed)));
        }
    }

    private static function validateRejectionReason(string $rejectionReason): void
    {
        $trimmed = trim($rejectionReason);
        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Rejection reason cannot be empty');
        }

        if (strlen($trimmed) < 20) {
            throw new \InvalidArgumentException('Rejection reason must be at least 20 characters for GDPR compliance');
        }

        if (strlen($trimmed) > 2000) {
            throw new \InvalidArgumentException(sprintf('Rejection reason too long. Maximum 2000 characters. Got: %d', strlen($trimmed)));
        }
    }

    // Getters
    public function id(): DataSubjectRequestId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function requestType(): RequestType
    {
        return $this->requestType;
    }

    public function status(): RequestStatus
    {
        return $this->status;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function reviewNotes(): ?string
    {
        return $this->reviewNotes;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function exportData(): ?array
    {
        return $this->exportData;
    }

    public function submittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function deadline(): \DateTimeImmutable
    {
        return $this->deadline;
    }

    public function isExtended(): bool
    {
        return $this->isExtended;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
