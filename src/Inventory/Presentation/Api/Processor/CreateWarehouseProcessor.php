<?php

declare(strict_types=1);

namespace App\Inventory\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Inventory\Application\Command\CreateWarehouse\CreateWarehouse;
use App\Inventory\Application\Query\GetWarehouseById\GetWarehouseById;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Inventory\Presentation\Api\Transformer\WarehouseResourceTransformer;
use App\Inventory\Presentation\Api\WarehouseResource;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProcessorInterface<WarehouseResource, WarehouseResource>
 */
final readonly class CreateWarehouseProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): WarehouseResource {
        if (!$data instanceof WarehouseResource) {
            throw new \InvalidArgumentException('Expected WarehouseResource');
        }

        if (null === $data->code || null === $data->name || null === $data->address) {
            throw new \InvalidArgumentException('Code, name, and address are required');
        }

        // Get tenant ID from context (set by TenantContextProvider)
        $tenantId = $context['tenant_id'] ?? null;
        if (null === $tenantId) {
            throw new \RuntimeException('Tenant ID not found in context');
        }

        $warehouseId = WarehouseId::generate();

        // Dispatch command to create warehouse
        $command = new CreateWarehouse(
            $warehouseId,
            TenantId::fromString($tenantId),
            WarehouseCode::fromString($data->code),
            WarehouseName::fromString($data->name),
            Address::fromArray($data->address),
            $data->priority,
        );

        $this->commandBus->dispatch($command);

        // Query to get the created warehouse
        $query = new GetWarehouseById($warehouseId);
        $envelope = $this->queryBus->dispatch($query);
        $stamp = $envelope->last(HandledStamp::class);

        if (!$stamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        $warehouseDTO = $stamp->getResult();

        if (null === $warehouseDTO) {
            throw new \RuntimeException('Warehouse not found after creation');
        }

        return WarehouseResourceTransformer::fromDTO($warehouseDTO);
    }
}
