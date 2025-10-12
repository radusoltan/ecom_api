<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\GetAllWarehouses;

use App\Inventory\Application\DTO\WarehouseDTO;
use App\Inventory\Domain\Repository\WarehouseRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAllWarehousesHandler
{
    public function __construct(
        private WarehouseRepositoryInterface $warehouseRepository,
    ) {}

    /**
     * @return WarehouseDTO[]
     */
    public function __invoke(GetAllWarehouses $query): array
    {
        $warehouses = $query->activeOnly
            ? $this->warehouseRepository->findActiveByTenant($query->tenantId)
            : $this->warehouseRepository->findByTenant($query->tenantId);

        return array_map(
            fn ($warehouse) => WarehouseDTO::fromDomainModel($warehouse),
            $warehouses
        );
    }
}
