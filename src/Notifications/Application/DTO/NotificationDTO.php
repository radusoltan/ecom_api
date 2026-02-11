<?php

declare(strict_types=1);

namespace App\Notifications\Application\DTO;

use App\Notifications\Domain\Model\Notification;

/**
 * Notification Data Transfer Object.
 *
 * Used for read operations and API responses.
 */
final readonly class NotificationDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $type,
        public ?string $recipientEmail,
        public ?string $recipientPhone,
        public string $subject,
        public string $body,
        public string $status,
        public int $attemptCount,
        public ?string $sentAt,
        public ?string $failedAt,
        public ?string $failureReason,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public static function fromDomainModel(Notification $notification): self
    {
        return new self(
            id: $notification->id()->toString(),
            tenantId: $notification->tenantId()->toString(),
            type: $notification->type()->value,
            recipientEmail: $notification->recipientEmail(),
            recipientPhone: $notification->recipientPhone(),
            subject: $notification->subject(),
            body: $notification->body(),
            status: $notification->status()->value,
            attemptCount: $notification->attemptCount(),
            sentAt: $notification->sentAt()?->format(\DateTimeInterface::ATOM),
            failedAt: $notification->failedAt()?->format(\DateTimeInterface::ATOM),
            failureReason: $notification->failureReason(),
            createdAt: $notification->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $notification->updatedAt()->format(\DateTimeInterface::ATOM)
        );
    }
}
