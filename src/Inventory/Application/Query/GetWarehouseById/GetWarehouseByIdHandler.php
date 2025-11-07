<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\GetWarehouseById;

use App\Inventory\Application\DTO\WarehouseDTO;
use App\Inventory\Domain\Repository\WarehouseRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetWarehouseByIdHandler
{
    public function __construct(
        private WarehouseRepositoryInterface $warehouseRepository,
    ) {
    }

    public function __invoke(GetWarehouseById $query): ?WarehouseDTO
    {
        $warehouse = $this->warehouseRepository->findById($query->id);

        return $warehouse ? WarehouseDTO::fromDomainModel($warehouse) : null;
    }
}
