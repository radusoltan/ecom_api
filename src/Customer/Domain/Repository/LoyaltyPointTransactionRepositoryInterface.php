<?php

declare(strict_types=1);

namespace App\Customer\Domain\Repository;

use App\Customer\Domain\Model\LoyaltyPointTransaction;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\LoyaltyPointTransactionId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Loyalty Point Transaction Repository Interface.
 *
 * Provides methods to persist and retrieve loyalty point transactions.
 */
interface LoyaltyPointTransactionRepositoryInterface
{
    /**
     * Save a loyalty point transaction.
     */
    public function save(LoyaltyPointTransaction $transaction): void;

    /**
     * Find a transaction by its ID.
     */
    public function findById(LoyaltyPointTransactionId $id): ?LoyaltyPointTransaction;

    /**
     * Find all transactions for a customer (ordered by created_at DESC).
     *
     * @return array<LoyaltyPointTransaction>
     */
    public function findByCustomerId(CustomerId $customerId, TenantId $tenantId): array;

    /**
     * Find transactions for a customer with pagination.
     *
     * @return array<LoyaltyPointTransaction>
     */
    public function findByCustomerIdPaginated(
        CustomerId $customerId,
        TenantId $tenantId,
        int $page,
        int $limit,
    ): array;

    /**
     * Count total transactions for a customer.
     */
    public function countByCustomerId(CustomerId $customerId, TenantId $tenantId): int;

    /**
     * Calculate the sum of all points for a customer.
     * This should match the customer's loyalty_points_balance.
     */
    public function sumPointsByCustomerId(CustomerId $customerId, TenantId $tenantId): int;
}
