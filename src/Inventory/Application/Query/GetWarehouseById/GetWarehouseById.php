<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\GetWarehouseById;

use App\Inventory\Domain\Model\WarehouseId;

final readonly class GetWarehouseById
{
    public function __construct(
        public WarehouseId $id,
    ) {}
}
