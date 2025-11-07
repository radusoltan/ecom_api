<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\CreateWarehouse;

use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class CreateWarehouse
{
    public function __construct(
        public WarehouseId $id,
        public TenantId $tenantId,
        public WarehouseCode $code,
        public WarehouseName $name,
        public Address $address,
        public ?int $priority = null,
    ) {
    }
}
