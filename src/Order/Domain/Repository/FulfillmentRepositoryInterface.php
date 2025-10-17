<?php

declare(strict_types=1);

namespace App\Order\Domain\Repository;

use App\Inventory\Domain\Model\WarehouseId;
use App\Order\Domain\Model\Fulfillment;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Order\Domain\ValueObject\FulfillmentStatus;
use App\Shared\Domain\ValueObject\TenantId;

interface FulfillmentRepositoryInterface
{
    /**
     * Save fulfillment aggregate
     */
    public function save(Fulfillment $fulfillment): void;

    /**
     * Find fulfillment by ID
     */
    public function findById(FulfillmentId $id): ?Fulfillment;

    /**
     * Find fulfillment by order ID
     */
    public function findByOrderId(OrderId $orderId): ?Fulfillment;

    /**
     * Find all fulfillments for a tenant
     *
     * @return Fulfillment[]
     */
    public function findByTenant(TenantId $tenantId): array;

    /**
     * Find fulfillments by status
     *
     * @return Fulfillment[]
     */
    public function findByStatus(FulfillmentStatus $status, TenantId $tenantId): array;

    /**
     * Find fulfillments by warehouse
     *
     * @return Fulfillment[]
     */
    public function findByWarehouse(WarehouseId $warehouseId, TenantId $tenantId): array;

    /**
     * Find all in-progress fulfillments (not in final state)
     *
     * @return Fulfillment[]
     */
    public function findInProgress(TenantId $tenantId): array;
}
