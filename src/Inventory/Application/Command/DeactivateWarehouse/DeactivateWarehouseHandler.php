<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\DeactivateWarehouse;

use App\Inventory\Domain\Repository\WarehouseRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeactivateWarehouseHandler
{
    public function __construct(
        private WarehouseRepositoryInterface $warehouseRepository,
    ) {}

    public function __invoke(DeactivateWarehouse $command): void
    {
        $warehouse = $this->warehouseRepository->findById($command->id);

        if ($warehouse === null) {
            throw new \DomainException('Warehouse not found');
        }

        $warehouse->deactivate();

        $this->warehouseRepository->save($warehouse);
    }
}
