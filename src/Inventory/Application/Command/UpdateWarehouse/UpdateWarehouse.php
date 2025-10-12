<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\UpdateWarehouse;

use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Shared\Domain\ValueObject\Address;

final readonly class UpdateWarehouse
{
    public function __construct(
        public WarehouseId $id,
        public WarehouseName $name,
        public Address $address,
        public int $priority,
    ) {}
}
