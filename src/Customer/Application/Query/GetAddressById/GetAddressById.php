<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetAddressById;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Get Address By ID Query.
 *
 * Query to retrieve a single address by its ID.
 */
final readonly class GetAddressById
{
    public function __construct(
        public string $addressId,
        public CustomerId $customerId,
        public TenantId $tenantId
    ) {
    }
}
