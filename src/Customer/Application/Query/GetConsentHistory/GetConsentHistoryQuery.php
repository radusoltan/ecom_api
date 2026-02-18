<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetConsentHistory;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Get Consent History Query.
 *
 * Retrieves paginated consent history for audit purposes.
 */
final readonly class GetConsentHistoryQuery
{
    public function __construct(
        public CustomerId $customerId,
        public TenantId $tenantId,
        public int $page = 1,
        public int $limit = 20,
    ) {
    }
}
