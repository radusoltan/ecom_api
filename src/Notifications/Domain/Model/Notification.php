<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Model;

use App\Notifications\Domain\Event\NotificationCreated;
use App\Notifications\Domain\Event\NotificationFailed;
use App\Notifications\Domain\Event\NotificationSent;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Notification Aggregate Root.
 *
 * Business Rules:
 * - Notification must have a valid type (email, sms, webhook, push)
 * - Email notifications require recipientEmail
 * - SMS notifications require recipientPhone
 * - Cannot modify notification after it's sent or cancelled
 * - Failed notifications can be retried up to 3 times
 * - Subject and body are required for all notification types
 */
final class Notification extends AggregateRoot
{
    private const MAX_RETRY_ATTEMPTS = 3;

    private NotificationId $id;
    private TenantId $tenantId;
    private NotificationType $type;
    private ?string $recipientEmail;
    private ?string $recipientPhone;
    private string $subject;
    private string $body;
    private NotificationStatus $status;
    private int $attemptCount;
    private ?\DateTimeImmutable $sentAt;
    private ?\DateTimeImmutable $failedAt;
    private ?string $failureReason;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(
        NotificationId $id,
        TenantId $tenantId,
        NotificationType $type,
        ?string $recipientEmail,
        ?string $recipientPhone,
        string $subject,
        string $body,
    ): self {
        // Validate recipient based on type
        if ($type->isEmail() && (null === $recipientEmail || '' === $recipientEmail)) {
            throw new \InvalidArgumentException('Email notifications require a recipient email address');
        }

        if ($type->isSms() && (null === $recipientPhone || '' === $recipientPhone)) {
            throw new \InvalidArgumentException('SMS notifications require a recipient phone number');
        }

        if ($type->isEmail() && null !== $recipientEmail && false === filter_var($recipientEmail, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(sprintf('Invalid recipient email: "%s"', $recipientEmail));
        }

        if ('' === trim($subject)) {
            throw new \InvalidArgumentException('Notification subject cannot be empty');
        }

        if ('' === trim($body)) {
            throw new \InvalidArgumentException('Notification body cannot be empty');
        }

        $notification = new self();
        $notification->id = $id;
        $notification->tenantId = $tenantId;
        $notification->type = $type;
        $notification->recipientEmail = $recipientEmail;
        $notification->recipientPhone = $recipientPhone;
        $notification->subject = $subject;
        $notification->body = $body;
        $notification->status = NotificationStatus::PENDING;
        $notification->attemptCount = 0;
        $notification->sentAt = null;
        $notification->failedAt = null;
        $notification->failureReason = null;
        $notification->createdAt = new \DateTimeImmutable();
        $notification->updatedAt = new \DateTimeImmutable();

        $notification->recordEvent(new NotificationCreated(
            $notification->id,
            $notification->tenantId,
            $notification->type,
            $notification->recipientEmail,
            $notification->subject
        ));

        return $notification;
    }

    public function markAsSent(): void
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException(sprintf('Cannot mark notification as sent. Current status: %s', $this->status->value));
        }

        $this->status = NotificationStatus::SENT;
        $this->sentAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new NotificationSent(
            $this->id,
            $this->tenantId,
            $this->type,
            $this->recipientEmail,
            $this->sentAt
        ));
    }

    public function markAsFailed(string $reason): void
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException(sprintf('Cannot mark notification as failed. Current status: %s', $this->status->value));
        }

        if ('' === trim($reason)) {
            throw new \InvalidArgumentException('Failure reason cannot be empty');
        }

        $this->status = NotificationStatus::FAILED;
        $this->failedAt = new \DateTimeImmutable();
        $this->failureReason = $reason;
        ++$this->attemptCount;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new NotificationFailed(
            $this->id,
            $this->tenantId,
            $this->type,
            $this->recipientEmail,
            $reason,
            $this->attemptCount
        ));
    }

    public function retry(): void
    {
        if (!$this->status->canRetry()) {
            throw new \DomainException(sprintf('Cannot retry notification. Current status: %s', $this->status->value));
        }

        if ($this->attemptCount >= self::MAX_RETRY_ATTEMPTS) {
            throw new \DomainException(sprintf('Maximum retry attempts (%d) reached', self::MAX_RETRY_ATTEMPTS));
        }

        $this->status = NotificationStatus::PENDING;
        $this->failedAt = null;
        $this->failureReason = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException(sprintf('Cannot cancel notification. Current status: %s', $this->status->value));
        }

        $this->status = NotificationStatus::CANCELLED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function reconstituteFromPersistence(
        NotificationId $id,
        TenantId $tenantId,
        NotificationType $type,
        ?string $recipientEmail,
        ?string $recipientPhone,
        string $subject,
        string $body,
        NotificationStatus $status,
        int $attemptCount,
        ?\DateTimeImmutable $sentAt,
        ?\DateTimeImmutable $failedAt,
        ?string $failureReason,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $notification = new self();
        $notification->id = $id;
        $notification->tenantId = $tenantId;
        $notification->type = $type;
        $notification->recipientEmail = $recipientEmail;
        $notification->recipientPhone = $recipientPhone;
        $notification->subject = $subject;
        $notification->body = $body;
        $notification->status = $status;
        $notification->attemptCount = $attemptCount;
        $notification->sentAt = $sentAt;
        $notification->failedAt = $failedAt;
        $notification->failureReason = $failureReason;
        $notification->createdAt = $createdAt;
        $notification->updatedAt = $updatedAt;

        return $notification;
    }

    // Getters
    public function id(): NotificationId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function type(): NotificationType
    {
        return $this->type;
    }

    public function recipientEmail(): ?string
    {
        return $this->recipientEmail;
    }

    public function recipientPhone(): ?string
    {
        return $this->recipientPhone;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): NotificationStatus
    {
        return $this->status;
    }

    public function attemptCount(): int
    {
        return $this->attemptCount;
    }

    public function sentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function failedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
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
