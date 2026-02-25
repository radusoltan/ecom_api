<?php

declare(strict_types=1);

namespace App\Order\Application\EventSubscriber;

use App\Notifications\Application\Command\SendNotification\SendNotification;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationType;
use App\Order\Application\Command\StartFulfillment;
use App\Order\Domain\Event\FulfillmentCannotBeProcessed;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Order\Domain\Service\WarehouseRoutingService;
use App\Order\Domain\ValueObject\FulfillmentId;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Order Placed Fulfillment Subscriber.
 *
 * Automatically initiates fulfillment when an order is placed.
 *
 * Workflow:
 * 1. Order placed → Find best warehouse
 * 2. If warehouse found → Start fulfillment
 * 3. If no warehouse → Emit event + notify admin (manual intervention required)
 */
final readonly class OrderPlacedFulfillmentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private WarehouseRoutingService $routingService,
        private OrderRepositoryInterface $orderRepository,
        private MessageBusInterface $commandBus,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private string $adminEmail = 'admin@ecommerce.local',
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
            $reason = sprintf('No warehouse available to fulfill order %s', $orderId->toString());

            $this->logger->error('No warehouse available for order fulfillment', [
                'order_id' => $orderId->toString(),
                'tenant_id' => $tenantId->toString(),
                'order_lines_count' => count($order->lines()),
            ]);

            // Emit domain event for other subscribers to react
            $this->eventDispatcher->dispatch(new FulfillmentCannotBeProcessed(
                orderId: $orderId,
                tenantId: $tenantId,
                reason: $reason,
                occurredOn: new \DateTimeImmutable(),
            ));

            // Notify admin/operations team
            $this->commandBus->dispatch(new SendNotification(
                id: NotificationId::generate(),
                tenantId: $tenantId,
                type: NotificationType::EMAIL,
                recipientEmail: $this->adminEmail,
                recipientPhone: null,
                subject: sprintf('Fulfillment blocked: Order %s requires manual intervention', $orderId->toString()),
                body: sprintf(
                    "Order %s cannot be automatically fulfilled.\n\nReason: %s\nOrder lines: %d\nTenant: %s\n\nPlease investigate warehouse availability and process this order manually.",
                    $orderId->toString(),
                    $reason,
                    count($order->lines()),
                    $tenantId->toString(),
                ),
            ));

            return;
        }

        // Start fulfillment automatically
        $this->commandBus->dispatch(new StartFulfillment(
            fulfillmentId: FulfillmentId::generate(),
            orderId: $orderId,
            warehouseId: $warehouseId,
            tenantId: $tenantId,
        ));

        $this->logger->info('Fulfillment started for order', [
            'order_id' => $orderId->toString(),
            'warehouse_id' => $warehouseId->toString(),
            'tenant_id' => $tenantId->toString(),
        ]);
    }
}
