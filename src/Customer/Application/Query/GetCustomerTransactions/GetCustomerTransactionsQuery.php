<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetCustomerTransactions;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Get Customer Transactions Query.
 *
 * Retrieves paginated loyalty point transaction history for a customer.
 */
final readonly class GetCustomerTransactionsQuery
{
    public function __construct(
        public CustomerId $customerId,
        public TenantId $tenantId,
        public int $page = 1,
        public int $limit = 20
    ) {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }

        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Limit must be between 1 and 100');
        }
    }
}
