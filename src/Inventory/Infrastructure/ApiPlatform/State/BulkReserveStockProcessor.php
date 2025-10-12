<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Command\BulkReserveStock\BulkReserveStockCommand;
use App\Inventory\Application\Command\BulkReserveStock\BulkReserveStockItem;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Infrastructure\ApiPlatform\Resource\BulkStockOperationResource;
use App\Inventory\Infrastructure\ApiPlatform\Resource\BulkStockOperationResultItem;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<BulkStockOperationResource>
 */
final class BulkReserveStockProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
    ) {
        $this->messageBus = $messageBus;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BulkStockOperationResource
    {
        assert($data instanceof BulkStockOperationResource);

        $tenantId = $this->getTenantIdFromContext($context);

        // Convert API resource items to command items
        $commandItems = array_map(
            fn($item) => new BulkReserveStockItem(
                ProductId::fromString($item->productId),
                WarehouseId::fromString($item->warehouseId),
                Quantity::fromInt($item->quantity)
            ),
            $data->items
        );

        // Execute bulk reservation
        $command = new BulkReserveStockCommand(
            items: $commandItems,
            referenceId: $data->referenceId,
            tenantId: $tenantId
        );

        $result = $this->handle($command);

        // Convert result items to API resource
        $resultItems = array_merge(
            array_map(
                fn($item) => new BulkStockOperationResultItem(
                    productId: $item->productId,
                    warehouseId: $item->warehouseId,
                    quantity: $item->quantity,
                    success: $item->success,
                    error: $item->error,
                    availableQuantity: $item->availableQuantity,
                ),
                $result->reservedItems
            ),
            array_map(
                fn($item) => new BulkStockOperationResultItem(
                    productId: $item->productId,
                    warehouseId: $item->warehouseId,
                    quantity: $item->quantity,
                    success: $item->success,
                    error: $item->error,
                    availableQuantity: $item->availableQuantity,
                ),
                $result->failedItems
            )
        );

        // Determine status
        $status = match (true) {
            $result->isFullySuccessful() => 'success',
            $result->isPartiallySuccessful() => 'partial',
            $result->isFullyFailed() => 'failed',
            default => 'unknown',
        };

        return new BulkStockOperationResource(
            items: $data->items,
            referenceId: $result->referenceId,
            totalItems: $result->totalItems,
            successCount: $result->successCount,
            failureCount: $result->failureCount,
            results: $resultItems,
            status: $status,
        );
    }

    private function getTenantIdFromContext(array $context): TenantId
    {
        if (isset($context['tenant_id'])) {
            return TenantId::fromString($context['tenant_id']);
        }

        throw new \RuntimeException('Tenant ID not found in context. Ensure X-Tenant-ID header is provided.');
    }
}
