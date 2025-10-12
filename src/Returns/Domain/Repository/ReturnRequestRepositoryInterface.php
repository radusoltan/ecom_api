<?php

declare(strict_types=1);

namespace App\Returns\Domain\Repository;

use App\Order\Domain\Model\OrderId;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Repository Interface for ReturnRequest aggregate.
 *
 * Port in Hexagonal Architecture.
 * Implementations are in Infrastructure layer.
 */
interface ReturnRequestRepositoryInterface
{
    /**
     * Save a return request (insert or update).
     */
    public function save(ReturnRequest $returnRequest): void;

    /**
     * Find a return request by its ID.
     */
    public function findById(ReturnRequestId $id): ?ReturnRequest;

    /**
     * Find all return requests for a specific order.
     *
     * @return array<ReturnRequest>
     */
    public function findByOrderId(OrderId $orderId): array;

    /**
     * Find all return requests for a tenant.
     *
     * @return array<ReturnRequest>
     */
    public function findByTenantId(TenantId $tenantId): array;

    /**
     * Find all return requests with a specific status.
     *
     * @param string $status One of ReturnStatus constants
     * @return array<ReturnRequest>
     */
    public function findByStatus(string $status, TenantId $tenantId): array;
}
