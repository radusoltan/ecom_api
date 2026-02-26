<?php

declare(strict_types=1);

namespace App\Shipping\Domain\Repository;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Model\Shipment;
use App\Shipping\Domain\Model\ShipmentId;

interface ShipmentRepositoryInterface
{
    public function save(Shipment $shipment): void;

    public function findById(ShipmentId $id): ?Shipment;

    public function findByIdAndTenant(ShipmentId $id, TenantId $tenantId): ?Shipment;

    public function findByOrderId(string $orderId, TenantId $tenantId): ?Shipment;

    /** @return Shipment[] */
    public function findAllByTenant(TenantId $tenantId): array;
}
