<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\ActivateWarehouse;

use App\Inventory\Domain\Repository\WarehouseRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ActivateWarehouseHandler
{
    public function __construct(
        private WarehouseRepositoryInterface $warehouseRepository,
    ) {
    }

    public function __invoke(ActivateWarehouse $command): void
    {
        $warehouse = $this->warehouseRepository->findById($command->id);

        if (null === $warehouse) {
            throw new \DomainException('Warehouse not found');
        }

        $warehouse->activate();

        $this->warehouseRepository->save($warehouse);
    }
}
