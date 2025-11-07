<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Command\CreateStockItem\CreateStockItemCommand;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Infrastructure\ApiPlatform\Resource\StockItemResource;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<StockItemResource>
 */
final readonly class CreateStockItemProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StockItemResource
    {
        assert($data instanceof StockItemResource);

        $stockItemId = StockItemId::generate();

        // Get tenant from context (injected by TenantContextProcessor decorator)
        // Use data->tenantId as fallback for backward compatibility
        $tenantId = isset($context['tenant_id'])
            ? TenantId::fromString($context['tenant_id'])
            : TenantId::fromString($data->tenantId);

        $command = new CreateStockItemCommand(
            $stockItemId,
            ProductId::fromString($data->productId),
            WarehouseId::fromString($data->warehouseId),
            Quantity::fromInt($data->initialQuantity),
            null !== $data->lowStockThreshold ? Quantity::fromInt($data->lowStockThreshold) : null,
            $tenantId
        );

        $this->messageBus->dispatch($command);

        // Return created resource
        return new StockItemResource(
            id: $stockItemId->toString(),
            tenantId: $data->tenantId,
            productId: $data->productId,
            warehouseId: $data->warehouseId,
            initialQuantity: $data->initialQuantity,
            lowStockThreshold: $data->lowStockThreshold ?? 10,
            onHand: $data->initialQuantity,
            reserved: 0,
            allocated: 0,
            available: $data->initialQuantity,
            isLowStock: $data->initialQuantity <= ($data->lowStockThreshold ?? 10),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
