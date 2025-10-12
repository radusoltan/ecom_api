<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Command\ReserveStock\ReserveStockCommand;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\StockReservation;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Inventory\Domain\Repository\StockReservationRepositoryInterface;
use App\Inventory\Infrastructure\ApiPlatform\Resource\StockOperationResource;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<StockOperationResource>
 */
final readonly class ReserveStockProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private StockItemRepositoryInterface $stockItemRepository,
        private StockReservationRepositoryInterface $reservationRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StockOperationResource
    {
        assert($data instanceof StockOperationResource);

        // Get tenant from context
        $tenantId = $this->getTenantIdFromContext($context);

        // Find stock item
        $stockItem = $this->stockItemRepository->findByProductAndWarehouse(
            ProductId::fromString($data->productId),
            WarehouseId::fromString($data->warehouseId),
            $tenantId
        );

        if ($stockItem === null) {
            throw new \RuntimeException(sprintf(
                'Stock item not found for product %s in warehouse %s',
                $data->productId,
                $data->warehouseId
            ));
        }

        // Reserve stock
        $command = new ReserveStockCommand(
            ProductId::fromString($data->productId),
            WarehouseId::fromString($data->warehouseId),
            Quantity::fromInt($data->quantity),
            $data->referenceId, // reservation/cart ID
            $tenantId
        );

        $this->messageBus->dispatch($command);

        // Create reservation tracking
        $reservation = StockReservation::create(
            $data->referenceId,
            $stockItem->id(),
            $tenantId,
            Quantity::fromInt($data->quantity)
        );

        $this->reservationRepository->save($reservation);

        // Reload stock item to get updated quantities
        $stockItem = $this->stockItemRepository->findById($stockItem->id());
        $available = $stockItem->calculateAvailable();

        return new StockOperationResource(
            productId: $data->productId,
            warehouseId: $data->warehouseId,
            quantity: $data->quantity,
            referenceId: $data->referenceId,
            message: sprintf('Reserved %d units. Available: %d', $data->quantity, $available->value()),
            availableQuantity: $available->value(),
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
