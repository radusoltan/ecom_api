<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetDeletionRequestStatus;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetDeletionRequestStatusQuery
{
    public function __construct(
        public CustomerId $customerId,
        public TenantId $tenantId,
    ) {
    }
}
