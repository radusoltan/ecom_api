<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Metrics;

use App\Catalog\Domain\Event\CategoryCreated;
use App\Catalog\Domain\Event\ProductCreated;
use App\Shared\Infrastructure\Metrics\PrometheusMetricsCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber that records Prometheus metrics for catalog domain events.
 *
 * Automatically increments counters when:
 * - Products are created
 * - Categories are created
 *
 * This provides real-time visibility into catalog operations per tenant.
 */
final readonly class CatalogMetricsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PrometheusMetricsCollector $metricsCollector
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductCreated::class => 'onProductCreated',
            CategoryCreated::class => 'onCategoryCreated',
        ];
    }

    public function onProductCreated(ProductCreated $event): void
    {
        $this->metricsCollector->incrementProductCreated(
            $event->tenantId->toString()
        );
    }

    public function onCategoryCreated(CategoryCreated $event): void
    {
        $this->metricsCollector->incrementCategoryCreated(
            $event->tenantId->toString()
        );
    }
}
