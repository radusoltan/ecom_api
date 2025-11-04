<?php

declare(strict_types=1);

namespace App\Customer\Application\Query;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetCustomerOrdersQuery
{
    public function __construct(
        private CustomerId $customerId,
        private TenantId $tenantId
    ) {
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }
}
