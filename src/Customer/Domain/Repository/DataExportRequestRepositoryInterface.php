<?php

declare(strict_types=1);

namespace App\Customer\Domain\Repository;

use App\Customer\Domain\Model\DataExportRequest;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Data Export Request Repository Interface.
 *
 * Port for persisting and retrieving data export requests.
 */
interface DataExportRequestRepositoryInterface
{
    /**
     * Save a data export request.
     */
    public function save(DataExportRequest $request): void;

    /**
     * Find a data export request by ID.
     */
    public function findById(DataExportRequestId $id): ?DataExportRequest;

    /**
     * Find all export requests for a customer.
     *
     * @return DataExportRequest[]
     */
    public function findByCustomerId(CustomerId $customerId, TenantId $tenantId): array;

    /**
     * Find the pending export request for a customer (if any).
     *
     * Used for rate limiting - only one pending request allowed per customer.
     */
    public function findPendingByCustomerId(CustomerId $customerId, TenantId $tenantId): ?DataExportRequest;

    /**
     * Count recent export requests by customer (for rate limiting).
     *
     * Returns the number of export requests created after the given timestamp.
     * Used to enforce the 1 request per 24 hours rule.
     */
    public function countRecentByCustomerId(CustomerId $customerId, \DateTimeImmutable $since): int;
}
