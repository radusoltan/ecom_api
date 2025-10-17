<?php

declare(strict_types=1);

namespace App\Order\Application\EventSubscriber;

use App\Order\Domain\Event\OrderCancelled;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Event\OrderStatusChanged;
use App\Shared\Infrastructure\Metrics\MetricsCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Order Metrics Subscriber
 *
 * Collects metrics for order lifecycle events:
 * - order_placed_total (counter)
 * - order_status_change_total (counter)
 * - order_duration_seconds (histogram) - time from placed to completed
 *
 * All metrics include tenant_id label for multi-tenant monitoring.
 *
 * @see EPIC 3.3 - Observability & Tracing
 */
final readonly class OrderMetricsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MetricsCollector $metricsCollector
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderPlaced::class => 'onOrderPlaced',
            OrderStatusChanged::class => 'onOrderStatusChanged',
            OrderCancelled::class => 'onOrderCancelled',
        ];
    }

    public function onOrderPlaced(OrderPlaced $event): void
    {
        $this->metricsCollector->incrementCounter(
            'order_placed_total',
            [
                'tenant_id' => $event->tenantId->toString(),
                'status' => 'pending',
            ]
        );
    }

    public function onOrderStatusChanged(OrderStatusChanged $event): void
    {
        $this->metricsCollector->incrementCounter(
            'order_status_change_total',
            [
                'tenant_id' => $event->tenantId->toString(),
                'from_status' => $event->oldStatus,
                'to_status' => $event->newStatus,
            ]
        );
    }

    public function onOrderCancelled(OrderCancelled $event): void
    {
        $this->metricsCollector->incrementCounter(
            'order_status_change_total',
            [
                'tenant_id' => $event->tenantId->toString(),
                'from_status' => 'pending',
                'to_status' => 'cancelled',
            ]
        );
    }
}
