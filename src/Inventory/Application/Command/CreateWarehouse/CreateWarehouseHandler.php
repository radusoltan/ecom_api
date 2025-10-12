<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\CreateWarehouse;

use App\Inventory\Domain\Model\Warehouse;
use App\Inventory\Domain\Repository\WarehouseRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateWarehouseHandler
{
    public function __construct(
        private WarehouseRepositoryInterface $warehouseRepository,
    ) {}

    public function __invoke(CreateWarehouse $command): void
    {
        // Check if warehouse code already exists for this tenant
        if ($this->warehouseRepository->codeExistsForTenant($command->code, $command->tenantId)) {
            throw new \DomainException(
                sprintf('Warehouse with code "%s" already exists for this tenant', $command->code)
            );
        }

        $warehouse = Warehouse::create(
            $command->id,
            $command->tenantId,
            $command->code,
            $command->name,
            $command->address,
            $command->priority,
        );

        $this->warehouseRepository->save($warehouse);
    }
}
