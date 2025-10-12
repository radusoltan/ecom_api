<?php

declare(strict_types=1);

namespace App\Inventory\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Inventory\Application\Command\UpdateWarehouse\UpdateWarehouse;
use App\Inventory\Application\Query\GetWarehouseById\GetWarehouseById;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Inventory\Presentation\Api\Transformer\WarehouseResourceTransformer;
use App\Inventory\Presentation\Api\WarehouseResource;
use App\Shared\Domain\ValueObject\Address;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProcessorInterface<WarehouseResource, WarehouseResource>
 */
final readonly class UpdateWarehouseProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
    ) {}

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): WarehouseResource {
        if (!$data instanceof WarehouseResource) {
            throw new \InvalidArgumentException('Expected WarehouseResource');
        }

        if (!isset($uriVariables['id'])) {
            throw new \InvalidArgumentException('Warehouse ID is required');
        }

        if (null === $data->name || null === $data->address || null === $data->priority) {
            throw new \InvalidArgumentException('Name, address, and priority are required');
        }

        $warehouseId = WarehouseId::fromString($uriVariables['id']);

        // Dispatch command to update warehouse
        $command = new UpdateWarehouse(
            $warehouseId,
            WarehouseName::fromString($data->name),
            Address::fromArray($data->address),
            $data->priority,
        );

        $this->commandBus->dispatch($command);

        // Query to get the updated warehouse
        $query = new GetWarehouseById($warehouseId);
        $envelope = $this->queryBus->dispatch($query);
        $stamp = $envelope->last(HandledStamp::class);

        if (!$stamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        $warehouseDTO = $stamp->getResult();

        if (null === $warehouseDTO) {
            throw new \RuntimeException('Warehouse not found');
        }

        return WarehouseResourceTransformer::fromDTO($warehouseDTO);
    }
}
