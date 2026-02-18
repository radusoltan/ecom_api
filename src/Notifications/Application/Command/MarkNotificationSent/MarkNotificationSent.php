<?php

declare(strict_types=1);

namespace App\Notifications\Application\Command\MarkNotificationSent;

use App\Notifications\Domain\Model\NotificationId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * MarkNotificationSent Command (DTO).
 */
final readonly class MarkNotificationSent
{
    public function __construct(
        public NotificationId $id,
        public TenantId $tenantId,
    ) {
    }
}
