<?php

declare(strict_types=1);

namespace App\Inventory\Presentation\Api\Transformer;

use App\Inventory\Application\DTO\WarehouseDTO;
use App\Inventory\Presentation\Api\WarehouseResource;

final class WarehouseResourceTransformer
{
    public static function fromDTO(WarehouseDTO $dto): WarehouseResource
    {
        $resource = new WarehouseResource();
        $resource->id = $dto->id;
        $resource->tenantId = $dto->tenantId;
        $resource->code = $dto->code;
        $resource->name = $dto->name;
        $resource->address = $dto->address;
        $resource->priority = $dto->priority;
        $resource->isActive = $dto->isActive;
        $resource->createdAt = $dto->createdAt;
        $resource->updatedAt = $dto->updatedAt;

        return $resource;
    }
}
