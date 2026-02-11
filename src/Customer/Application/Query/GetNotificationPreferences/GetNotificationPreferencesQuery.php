<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetNotificationPreferences;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Query to get customer notification preferences.
 */
final readonly class GetNotificationPreferencesQuery
{
    public function __construct(
        public CustomerId $customerId,
        public TenantId $tenantId
    ) {
    }
}
