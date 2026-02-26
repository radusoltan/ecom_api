<?php

declare(strict_types=1);

namespace App\Order\Application\Command;

use App\Inventory\Application\Command\AllocateStock\AllocateStockCommand;
use App\Inventory\Domain\Model\Quantity;
use App\Order\Domain\Model\Fulfillment;
use App\Order\Domain\Repository\FulfillmentRepositoryInterface;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Start Fulfillment Command Handler.
 *
 * Creates a new fulfillment record and allocates stock from inventory.
 *
 * Flow:
 * 1. Verify order exists
 * 2. Check no existing fulfillment
 * 3. Create fulfillment aggregate
 * 4. Allocate stock for each order line (via Inventory context)
 * 5. Persist fulfillment and dispatch events
 *
 * Cross-Context Communication:
 * - Uses command bus to dispatch AllocateStock to Inventory context
 * - Follows ACL pattern for bounded context isolation
 * - If stock allocation fails, fulfillment creation should be rolled back
 */
#[AsMessageHandler]
final readonly class StartFulfillmentHandler
{
    public function __construct(
        private FulfillmentRepositoryInterface $fulfillmentRepository,
        private OrderRepositoryInterface $orderRepository,
        private MessageBusInterface $commandBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(StartFulfillment $command): void
    {
        // Verify order exists
        $order = $this->orderRepository->findById($command->orderId);
        if (null === $order) {
            throw new \DomainException(sprintf('Order not found: %s', $command->orderId->toString()));
        }

        // Check if fulfillment already exists for this order
        $existingFulfillment = $this->fulfillmentRepository->findByOrderId($command->orderId);
        if (null !== $existingFulfillment) {
            throw new \DomainException(sprintf('Fulfillment already exists for order: %s', $command->orderId->toString()));
        }

        // Create and start fulfillment
        $fulfillment = Fulfillment::start(
            $command->fulfillmentId,
            $command->orderId,
            $command->warehouseId,
            $command->tenantId,
        );

        $this->fulfillmentRepository->save($fulfillment);

        // CRITICAL: Allocate stock for each order line
        // This communicates with Inventory bounded context via command bus (ACL pattern)
        $allocatedLines = 0;
        foreach ($order->lines() as $orderLine) {
            try {
                $this->commandBus->dispatch(new AllocateStockCommand(
                    productId: $orderLine->productId()->toString(),
                    warehouseId: $command->warehouseId,
                    quantity: Quantity::fromInt($orderLine->quantity()),
                    orderId: $command->orderId->toString(),
                    tenantId: $command->tenantId,
                ));

                ++$allocatedLines;

                $this->logger->debug('Stock allocated for order line', [
                    'order_id' => $command->orderId->toString(),
                    'product_id' => $orderLine->productId()->toString(),
                    'quantity' => $orderLine->quantity(),
                    'warehouse_id' => $command->warehouseId->toString(),
                ]);
            } catch (\DomainException $e) {
                // Stock allocation failed - log and re-throw
                $this->logger->error('Stock allocation failed for order line', [
                    'order_id' => $command->orderId->toString(),
                    'product_id' => $orderLine->productId()->toString(),
                    'quantity' => $orderLine->quantity(),
                    'warehouse_id' => $command->warehouseId->toString(),
                    'error' => $e->getMessage(),
                ]);

                // Re-throw to trigger transaction rollback
                throw $e;
            }
        }

        $this->logger->info('Fulfillment started with stock allocation', [
            'fulfillment_id' => $command->fulfillmentId->toString(),
            'order_id' => $command->orderId->toString(),
            'warehouse_id' => $command->warehouseId->toString(),
            'tenant_id' => $command->tenantId->toString(),
            'allocated_lines' => $allocatedLines,
            'total_lines' => count($order->lines()),
        ]);
    }
}
