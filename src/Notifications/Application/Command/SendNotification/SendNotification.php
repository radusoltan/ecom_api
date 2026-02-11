<?php

declare(strict_types=1);

namespace App\Notifications\Application\Command\SendNotification;

use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationType;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * SendNotification Command (DTO).
 */
final readonly class SendNotification
{
    public function __construct(
        public NotificationId $id,
        public TenantId $tenantId,
        public NotificationType $type,
        public ?string $recipientEmail,
        public ?string $recipientPhone,
        public string $subject,
        public string $body
    ) {
    }
}
