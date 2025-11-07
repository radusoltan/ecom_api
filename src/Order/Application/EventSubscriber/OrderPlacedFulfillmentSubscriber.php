<?php

declare(strict_types=1);

namespace App\Order\Application\EventSubscriber;

use App\Order\Application\Command\StartFulfillment;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Order\Domain\Service\WarehouseRoutingService;
use App\Order\Domain\ValueObject\FulfillmentId;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Order Placed Fulfillment Subscriber.
 *
 * Automatically initiates fulfillment when an order is placed.
 *
 * Workflow:
 * 1. Order placed → Find best warehouse
 * 2. If warehouse found → Start fulfillment
 * 3. If no warehouse → Log error (manual intervention required)
 */
final readonly class OrderPlacedFulfillmentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private WarehouseRoutingService $routingService,
        private OrderRepositoryInterface $orderRepository,
        private MessageBusInterface $commandBus,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderPlaced::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(OrderPlaced $event): void
    {
        $orderId = $event->orderId;
        $tenantId = $event->tenantId;

        // Fetch the order
        $order = $this->orderRepository->findById($orderId);
        if (null === $order) {
            $this->logger->error('Order not found for fulfillment', [
                'order_id' => $orderId->toString(),
                'tenant_id' => $tenantId->toString(),
            ]);

            return;
        }

        // Find best warehouse
        $warehouseId = $this->routingService->findBestWarehouse($order);

        if (null === $warehouseId) {
            $this->logger->error('No warehouse available for order fulfillment', [
                'order_id' => $orderId->toString(),
                'tenant_id' => $tenantId->toString(),
                'order_lines_count' => count($order->lines()),
            ]);

            // TODO: Emit FulfillmentCannotBeProcessed event
            // TODO: Send notification to admin/operations team
            return;
        }

        // Start fulfillment automatically
        // TODO: Re-enable when FulfillmentRepository is implemented
        /*
        $this->commandBus->dispatch(new StartFulfillment(
            fulfillmentId: FulfillmentId::generate(),
            orderId: $orderId,
            warehouseId: $warehouseId,
            tenantId: $tenantId,
        ));
        */

        $this->logger->info('Order placed, fulfillment pending implementation', [
            'order_id' => $orderId->toString(),
            'warehouse_id' => $warehouseId->toString(),
            'tenant_id' => $tenantId->toString(),
        ]);
    }
}
