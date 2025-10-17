<?php

declare(strict_types=1);

namespace App\Order\Application\EventSubscriber;

use App\Order\Application\Command\UpdateOrderStatus;
use App\Order\Domain\Event\FulfillmentCompleted;
use App\Order\Domain\Model\OrderStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Fulfillment Completed Subscriber
 *
 * Updates order status to "delivered" when fulfillment is completed.
 */
final readonly class FulfillmentCompletedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FulfillmentCompleted::class => 'onFulfillmentCompleted',
        ];
    }

    public function onFulfillmentCompleted(FulfillmentCompleted $event): void
    {
        // Update order status to delivered
        $this->commandBus->dispatch(new UpdateOrderStatus(
            orderId: $event->orderId,
            newStatus: OrderStatus::delivered(),
            tenantId: $event->tenantId,
        ));

        $this->logger->info('Order marked as delivered after fulfillment completion', [
            'fulfillment_id' => $event->fulfillmentId->toString(),
            'order_id' => $event->orderId->toString(),
            'tenant_id' => $event->tenantId->toString(),
        ]);
    }
}
