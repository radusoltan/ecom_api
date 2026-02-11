<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetCustomerConsents;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Get Customer Consents Query.
 *
 * Retrieves current GDPR consent status for a customer.
 */
final readonly class GetCustomerConsentsQuery
{
    public function __construct(
        public CustomerId $customerId,
        public TenantId $tenantId
    ) {
    }
}
