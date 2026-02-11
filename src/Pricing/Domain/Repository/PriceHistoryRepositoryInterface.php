<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Repository;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\ValueObject\PriceChange;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Domain\ValueObject\UserId;

/**
 * Repository interface for price history operations.
 *
 * This is a port (hexagonal architecture) that defines the contract
 * for persisting and retrieving price change history.
 */
interface PriceHistoryRepositoryInterface
{
    /**
     * Save a price change to the history.
     *
     * @param PriceChange $priceChange The price change to record
     * @param TenantId $tenantId The tenant this change belongs to
     * @param UserId|null $changedBy The user who made the change (null for system)
     * @return void
     */
    public function save(PriceChange $priceChange, TenantId $tenantId, ?UserId $changedBy = null): void;

    /**
     * Find all price changes for a specific product.
     *
     * @param ProductId $productId
     * @param TenantId $tenantId
     * @param int $limit Maximum number of records to return
     * @param int $offset Offset for pagination
     * @return array<PriceChange>
     */
    public function findByProductId(
        ProductId $productId,
        TenantId $tenantId,
        int $limit = 50,
        int $offset = 0
    ): array;

    /**
     * Find all price changes for a tenant.
     *
     * @param TenantId $tenantId
     * @param int $limit Maximum number of records to return
     * @param int $offset Offset for pagination
     * @return array<PriceChange>
     */
    public function findByTenant(TenantId $tenantId, int $limit = 50, int $offset = 0): array;

    /**
     * Find price changes within a date range.
     *
     * @param TenantId $tenantId
     * @param \DateTimeImmutable $from Start date (inclusive)
     * @param \DateTimeImmutable $to End date (inclusive)
     * @param int $limit Maximum number of records to return
     * @param int $offset Offset for pagination
     * @return array<PriceChange>
     */
    public function findByDateRange(
        TenantId $tenantId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        int $limit = 50,
        int $offset = 0
    ): array;

    /**
     * Get the latest price change for a product.
     *
     * @param ProductId $productId
     * @param TenantId $tenantId
     * @return PriceChange|null
     */
    public function getLatestForProduct(ProductId $productId, TenantId $tenantId): ?PriceChange;

    /**
     * Get the lowest price for a product in the last N days.
     * Required for EU consumer protection laws (must show lowest price in last 30 days).
     *
     * @param ProductId $productId
     * @param TenantId $tenantId
     * @param int $days Number of days to look back
     * @return PriceChange|null
     */
    public function getLowestPriceInLastDays(ProductId $productId, TenantId $tenantId, int $days = 30): ?PriceChange;

    /**
     * Count total price changes for a product.
     *
     * @param ProductId $productId
     * @param TenantId $tenantId
     * @return int
     */
    public function countByProductId(ProductId $productId, TenantId $tenantId): int;

    /**
     * Count total price changes for a tenant.
     *
     * @param TenantId $tenantId
     * @return int
     */
    public function countByTenant(TenantId $tenantId): int;

    /**
     * Delete price history older than specified date.
     * Used for data retention compliance (e.g., delete after 2 years).
     *
     * @param \DateTimeImmutable $beforeDate
     * @return int Number of records deleted
     */
    public function deleteOlderThan(\DateTimeImmutable $beforeDate): int;
}
