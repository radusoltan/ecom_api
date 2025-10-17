<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Metrics;

use App\Shared\Infrastructure\Tenant\TenantContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber that measures API request latency and records it to Prometheus.
 *
 * Measures the time between request start and response termination,
 * labeled by tenant and route for detailed performance monitoring.
 *
 * Only measures catalog API routes (starting with /api/) to avoid noise
 * from static assets, health checks, etc.
 */
final class ApiLatencyMiddleware implements EventSubscriberInterface
{
    private float $requestStartTime = 0.0;

    public function __construct(
        private readonly PrometheusMetricsCollector $metricsCollector,
        private readonly TenantContext $tenantContext
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024], // High priority
            KernelEvents::TERMINATE => ['onKernelTerminate', -1024], // Low priority
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Only measure API routes
        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        $this->requestStartTime = microtime(true);
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (0.0 === $this->requestStartTime) {
            return; // Not an API request or not a main request
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Skip metrics endpoint itself to avoid recursion
        if ($path === '/metrics') {
            return;
        }

        $latency = microtime(true) - $this->requestStartTime;

        // Get route name or use path as fallback
        $route = $request->attributes->get('_route', $path);

        // Get tenant ID if available
        $tenantId = 'unknown';
        if ($this->tenantContext->hasCurrentTenant()) {
            $tenantId = $this->tenantContext->getCurrentTenantId()->toString();
        }

        $this->metricsCollector->recordApiLatency($tenantId, $route, $latency);

        // Reset for next request
        $this->requestStartTime = 0.0;
    }
}
