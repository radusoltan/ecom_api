<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Command\AllocateStock\AllocateStockCommand;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Inventory\Domain\Repository\StockReservationRepositoryInterface;
use App\Inventory\Infrastructure\ApiPlatform\Resource\StockOperationResource;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<StockOperationResource>
 */
final readonly class AllocateStockProcessor implements ProcessorInterface
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

        $tenantId = $this->getTenantIdFromContext($context);

        // Find stock item
        $stockItem = $this->stockItemRepository->findByProductAndWarehouse(
            ProductId::fromString($data->productId),
            WarehouseId::fromString($data->warehouseId),
            $tenantId
        );

        if (null === $stockItem) {
            throw new NotFoundHttpException(sprintf('Stock item not found for product %s in warehouse %s', $data->productId, $data->warehouseId));
        }

        // Allocate stock (productId already validated by findByProductAndWarehouse above)
        $command = new AllocateStockCommand(
            (string) $data->productId,
            WarehouseId::fromString($data->warehouseId),
            Quantity::fromInt($data->quantity),
            $data->referenceId, // order ID
            $tenantId
        );

        try {
            $this->messageBus->dispatch($command);
        } catch (HandlerFailedException $exception) {
            $previous = $exception->getPrevious();
            if ($previous instanceof HttpExceptionInterface) {
                throw $previous;
            }

            throw new BadRequestHttpException($previous?->getMessage() ?? $exception->getMessage(), $exception);
        }

        // If there was a reservation, mark it as released
        $reservation = $this->reservationRepository->findByReservationId($data->referenceId);
        if (null !== $reservation && !$reservation->isReleased()) {
            $reservation->release();
            $this->reservationRepository->save($reservation);
        }

        // Reload stock item to get updated quantities
        $stockItem = $this->stockItemRepository->findById($stockItem->id());
        $available = $stockItem->calculateAvailable();

        return new StockOperationResource(
            productId: $data->productId,
            warehouseId: $data->warehouseId,
            quantity: $data->quantity,
            referenceId: $data->referenceId,
            message: sprintf('Allocated %d units for order %s. Available: %d', $data->quantity, $data->referenceId, $available->value()),
            availableQuantity: $available->value(),
        );
    }

    private function getTenantIdFromContext(array $context): TenantId
    {
        if (isset($context['tenant_id'])) {
            return TenantId::fromString($context['tenant_id']);
        }

        throw new BadRequestHttpException('Tenant ID not found in context. Ensure X-Tenant-ID header is provided.');
    }
}
