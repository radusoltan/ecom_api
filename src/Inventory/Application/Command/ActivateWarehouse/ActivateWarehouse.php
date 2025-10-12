<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\ActivateWarehouse;

use App\Inventory\Domain\Model\WarehouseId;

final readonly class ActivateWarehouse
{
    public function __construct(
        public WarehouseId $id,
    ) {}
}
