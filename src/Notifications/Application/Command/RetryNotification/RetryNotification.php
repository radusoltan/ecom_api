<?php

declare(strict_types=1);

namespace App\Notifications\Application\Command\RetryNotification;

use App\Notifications\Domain\Model\NotificationId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * RetryNotification Command (DTO).
 */
final readonly class RetryNotification
{
    public function __construct(
        public NotificationId $id,
        public TenantId $tenantId,
    ) {
    }
}
