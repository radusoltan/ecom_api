<?php

declare(strict_types=1);

namespace App\Inventory\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Inventory\Application\Query\GetWarehouseById\GetWarehouseById;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Presentation\Api\Transformer\WarehouseResourceTransformer;
use App\Inventory\Presentation\Api\WarehouseResource;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProviderInterface<WarehouseResource>
 */
final readonly class WarehouseItemProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
    ) {
    }

    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): ?WarehouseResource {
        $warehouseId = $uriVariables['id'] ?? null;

        if (null === $warehouseId) {
            throw new \InvalidArgumentException('Warehouse ID is required');
        }

        // Dispatch query to get warehouse by ID
        $query = new GetWarehouseById(WarehouseId::fromString($warehouseId));
        $envelope = $this->queryBus->dispatch($query);
        $stamp = $envelope->last(HandledStamp::class);

        if (!$stamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        $warehouseDTO = $stamp->getResult();

        if (null === $warehouseDTO) {
            return null;
        }

        return WarehouseResourceTransformer::fromDTO($warehouseDTO);
    }
}
