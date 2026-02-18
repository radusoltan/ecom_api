<?php

declare(strict_types=1);

namespace App\Notifications\Application\Query\GetNotificationsByTenant;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * GetNotificationsByTenant Query (DTO).
 */
final readonly class GetNotificationsByTenant
{
    public function __construct(
        public TenantId $tenantId,
    ) {
    }
}
