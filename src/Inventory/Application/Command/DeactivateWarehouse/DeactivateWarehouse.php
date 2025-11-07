<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\DeactivateWarehouse;

use App\Inventory\Domain\Model\WarehouseId;

final readonly class DeactivateWarehouse
{
    public function __construct(
        public WarehouseId $id,
    ) {
    }
}
