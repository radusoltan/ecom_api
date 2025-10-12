<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\UpdateWarehouse;

use App\Inventory\Domain\Repository\WarehouseRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateWarehouseHandler
{
    public function __construct(
        private WarehouseRepositoryInterface $warehouseRepository,
    ) {}

    public function __invoke(UpdateWarehouse $command): void
    {
        $warehouse = $this->warehouseRepository->findById($command->id);

        if ($warehouse === null) {
            throw new \DomainException('Warehouse not found');
        }

        $warehouse->update(
            $command->name,
            $command->address,
            $command->priority,
        );

        $this->warehouseRepository->save($warehouse);
    }
}
