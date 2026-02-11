<?php

declare(strict_types=1);

namespace App\Customer\Domain\Repository;

use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\ValueObject\CustomerSearchCriteria;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Customer Search Repository Interface.
 *
 * Provides search and filtering capabilities for customers.
 */
interface CustomerSearchRepositoryInterface
{
    /**
     * Search customers with filtering and pagination.
     *
     * @return array{customers: Customer[], total: int}
     */
    public function search(CustomerSearchCriteria $criteria): array;

    /**
     * Count customers by segment for a tenant.
     *
     * @return array<string, int> Segment name => count
     */
    public function countBySegment(TenantId $tenantId): array;

    /**
     * Count customers by status for a tenant.
     *
     * @return array{active: int, inactive: int}
     */
    public function countByStatus(TenantId $tenantId): array;
}
