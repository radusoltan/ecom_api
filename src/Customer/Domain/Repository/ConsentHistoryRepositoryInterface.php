<?php

declare(strict_types=1);

namespace App\Customer\Domain\Repository;

use App\Customer\Domain\Model\ConsentHistory;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Consent History Repository Interface.
 *
 * Port for consent history persistence operations.
 * Implementations must enforce multi-tenant isolation via PostgreSQL RLS.
 */
interface ConsentHistoryRepositoryInterface
{
    /**
     * Save consent history record.
     * Records are immutable - no updates, only inserts.
     */
    public function save(ConsentHistory $history): void;

    /**
     * Find all consent history records for a customer.
     *
     * @return array<ConsentHistory>
     */
    public function findByCustomerId(CustomerId $customerId, TenantId $tenantId): array;

    /**
     * Find consent history records for a customer with pagination.
     *
     * @return array<ConsentHistory>
     */
    public function findByCustomerIdPaginated(
        CustomerId $customerId,
        TenantId $tenantId,
        int $page = 1,
        int $limit = 20,
    ): array;
}
