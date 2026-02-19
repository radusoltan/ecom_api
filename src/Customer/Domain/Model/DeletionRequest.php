<?php

declare(strict_types=1);

namespace App\Customer\Domain\Model;

use App\Customer\Domain\Event\AccountDeletionCancelled;
use App\Customer\Domain\Event\AccountDeletionCompleted;
use App\Customer\Domain\Event\AccountDeletionConfirmed;
use App\Customer\Domain\Event\AccountDeletionHeld;
use App\Customer\Domain\Event\AccountDeletionRequested;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Domain\ValueObject\DeletionStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Deletion Request Entity.
 *
 * Represents a GDPR-compliant account deletion request (Right to be Forgotten).
 *
 * Business Rules:
 * - 30-day retention period before deletion (GDPR Article 17)
 * - Customer must confirm deletion request
 * - Cancellation possible until execution starts
 * - Admin can put on legal hold (litigation hold)
 * - Anonymization preserves order history for accounting
 * - Audit trail maintained
 */
final class DeletionRequest extends AggregateRoot
{
    private const RETENTION_DAYS = 30;

    private DeletionRequestId $id;
    private CustomerId $customerId;
    private TenantId $tenantId;
    private DeletionStatus $status;
    private ?string $reason;
    private ?string $holdReason;
    private \DateTimeImmutable $scheduledFor;
    private ?\DateTimeImmutable $confirmedAt;
    private ?\DateTimeImmutable $completedAt;
    private \DateTimeImmutable $createdAt;
    private ?string $privacyRequestId;

    private function __construct()
    {
    }

    public static function create(
        DeletionRequestId $id,
        CustomerId $customerId,
        TenantId $tenantId,
        ?string $reason = null,
        ?string $privacyRequestId = null,
    ): self {
        $request = new self();
        $request->id = $id;
        $request->customerId = $customerId;
        $request->tenantId = $tenantId;
        $request->status = DeletionStatus::PENDING;
        $request->reason = $reason;
        $request->holdReason = null;
        $request->confirmedAt = null;
        $request->completedAt = null;
        $request->privacyRequestId = $privacyRequestId;
        $request->createdAt = new \DateTimeImmutable();

        // Set initial scheduled date (will be updated when confirmed)
        $request->scheduledFor = $request->createdAt->modify('+'.self::RETENTION_DAYS.' days');

        $request->recordEvent(new AccountDeletionRequested(
            $request->id,
            $request->customerId,
            $request->tenantId,
            $request->reason
        ));

        return $request;
    }

    public static function reconstituteFromPersistence(
        DeletionRequestId $id,
        CustomerId $customerId,
        TenantId $tenantId,
        DeletionStatus $status,
        ?string $reason,
        ?string $holdReason,
        \DateTimeImmutable $scheduledFor,
        ?\DateTimeImmutable $confirmedAt,
        ?\DateTimeImmutable $completedAt,
        \DateTimeImmutable $createdAt,
        ?string $privacyRequestId = null,
    ): self {
        $request = new self();
        $request->id = $id;
        $request->customerId = $customerId;
        $request->tenantId = $tenantId;
        $request->status = $status;
        $request->reason = $reason;
        $request->holdReason = $holdReason;
        $request->scheduledFor = $scheduledFor;
        $request->confirmedAt = $confirmedAt;
        $request->completedAt = $completedAt;
        $request->createdAt = $createdAt;
        $request->privacyRequestId = $privacyRequestId;

        return $request;
    }

    public function confirm(): void
    {
        if (!$this->status->canTransitionTo(DeletionStatus::CONFIRMED)) {
            throw new \DomainException(sprintf('Cannot confirm deletion request in status: %s', $this->status->value));
        }

        $this->status = DeletionStatus::CONFIRMED;
        $this->confirmedAt = new \DateTimeImmutable();
        $this->scheduledFor = $this->confirmedAt->modify('+'.self::RETENTION_DAYS.' days');

        $this->recordEvent(new AccountDeletionConfirmed(
            $this->id,
            $this->customerId,
            $this->tenantId,
            $this->scheduledFor
        ));
    }

    public function cancel(): void
    {
        if (!$this->canBeCancelled()) {
            throw new \DomainException(sprintf('Cannot cancel deletion request in status: %s', $this->status->value));
        }

        $this->status = DeletionStatus::CANCELLED;

        $this->recordEvent(new AccountDeletionCancelled(
            $this->id,
            $this->customerId,
            $this->tenantId
        ));
    }

    public function putOnHold(string $holdReason): void
    {
        if (!$this->status->canTransitionTo(DeletionStatus::ON_HOLD)) {
            throw new \DomainException(sprintf('Cannot put deletion request on hold in status: %s', $this->status->value));
        }

        if (empty(trim($holdReason))) {
            throw new \InvalidArgumentException('Hold reason is required');
        }

        $this->status = DeletionStatus::ON_HOLD;
        $this->holdReason = $holdReason;

        $this->recordEvent(new AccountDeletionHeld(
            $this->id,
            $this->customerId,
            $this->tenantId,
            $this->holdReason
        ));
    }

    public function releaseHold(): void
    {
        if (!$this->status->isOnHold()) {
            throw new \DomainException('Deletion request is not on hold');
        }

        $this->status = DeletionStatus::CONFIRMED;
        $this->holdReason = null;
    }

    public function process(): void
    {
        if (!$this->canBeExecuted()) {
            throw new \DomainException(sprintf('Cannot process deletion request. Status: %s, Scheduled: %s, On Hold: %s', $this->status->value, $this->scheduledFor->format('Y-m-d H:i:s'), $this->isOnHold() ? 'Yes' : 'No'));
        }

        if (!$this->status->canTransitionTo(DeletionStatus::PROCESSING)) {
            throw new \DomainException(sprintf('Cannot process deletion request in status: %s', $this->status->value));
        }

        $this->status = DeletionStatus::PROCESSING;
    }

    public function complete(): void
    {
        if (!$this->status->canTransitionTo(DeletionStatus::COMPLETED)) {
            throw new \DomainException(sprintf('Cannot complete deletion request in status: %s', $this->status->value));
        }

        $this->status = DeletionStatus::COMPLETED;
        $this->completedAt = new \DateTimeImmutable();

        $this->recordEvent(new AccountDeletionCompleted(
            $this->id,
            $this->customerId,
            $this->tenantId
        ));
    }

    public function isOnHold(): bool
    {
        return $this->status->isOnHold();
    }

    public function canBeCancelled(): bool
    {
        // Can cancel only if pending, confirmed, or on hold
        return in_array($this->status, [
            DeletionStatus::PENDING,
            DeletionStatus::CONFIRMED,
            DeletionStatus::ON_HOLD,
        ], true);
    }

    public function canBeExecuted(): bool
    {
        // Can execute if confirmed, past scheduled date, and not on hold
        return DeletionStatus::CONFIRMED === $this->status
            && $this->scheduledFor <= new \DateTimeImmutable()
            && !$this->isOnHold();
    }

    // Getters
    public function id(): DeletionRequestId
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

    public function status(): DeletionStatus
    {
        return $this->status;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function holdReason(): ?string
    {
        return $this->holdReason;
    }

    public function scheduledFor(): \DateTimeImmutable
    {
        return $this->scheduledFor;
    }

    public function confirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function privacyRequestId(): ?string
    {
        return $this->privacyRequestId;
    }
}
