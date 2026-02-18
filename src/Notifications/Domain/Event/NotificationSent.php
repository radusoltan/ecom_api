<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Event;

use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationType;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class NotificationSent
{
    public function __construct(
        public NotificationId $notificationId,
        public TenantId $tenantId,
        public NotificationType $type,
        public ?string $recipientEmail,
        public \DateTimeImmutable $sentAt,
    ) {
    }
}
