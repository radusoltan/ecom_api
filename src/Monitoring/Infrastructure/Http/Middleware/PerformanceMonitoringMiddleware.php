<?php

declare(strict_types=1);

namespace App\Monitoring\Infrastructure\Http\Middleware;

use App\Monitoring\Infrastructure\Service\ApplicationPerformanceMonitor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Performance Monitoring Middleware.
 *
 * Automatically tracks performance metrics for all HTTP requests:
 * - Request/response timing
 * - Memory usage
 * - HTTP status codes
 * - Route-level performance
 * - Tenant-specific metrics
 *
 * Integrates with ApplicationPerformanceMonitor to provide:
 * - Real-time performance tracking
 * - Automatic alerting on threshold breaches
 * - Performance degradation detection
 */
final class PerformanceMonitoringMiddleware implements EventSubscriberInterface
{
    private const REQUEST_START_TIME_ATTR = '_perf_start_time';
    private const REQUEST_START_MEMORY_ATTR = '_perf_start_memory';

    public function __construct(
        private readonly ApplicationPerformanceMonitor $apm
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024], // High priority
            KernelEvents::RESPONSE => ['onKernelResponse', -1024], // Low priority
        ];
    }

    /**
     * Mark request start time.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Store start time and memory
        $request->attributes->set(self::REQUEST_START_TIME_ATTR, microtime(true));
        $request->attributes->set(self::REQUEST_START_MEMORY_ATTR, memory_get_usage(true));
    }

    /**
     * Track request completion and performance.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $startTime = $request->attributes->get(self::REQUEST_START_TIME_ATTR);

        if (null === $startTime) {
            return; // No start time recorded
        }

        // Calculate duration
        $durationMs = (microtime(true) - $startTime) * 1000;

        // Get route information
        $route = $request->attributes->get('_route', 'unknown');
        $method = $request->getMethod();
        $statusCode = $response->getStatusCode();

        // Get tenant ID if available
        $tenantId = $request->headers->get('X-Tenant-ID');

        // Track performance in APM
        $this->apm->trackApiRequest(
            route: $route,
            method: $method,
            durationMs: $durationMs,
            statusCode: $statusCode,
            tenantId: $tenantId
        );

        // Add performance headers to response (useful for debugging)
        if ($this->shouldAddPerformanceHeaders($request)) {
            $response->headers->set('X-Response-Time', sprintf('%.2fms', $durationMs));
            $response->headers->set('X-Memory-Usage', sprintf('%.2fMB', memory_get_usage(true) / 1024 / 1024));
        }
    }

    /**
     * Determine if performance headers should be added.
     */
    private function shouldAddPerformanceHeaders($request): bool
    {
        // Add headers in dev environment or if specifically requested
        return 'dev' === $_ENV['APP_ENV'] || $request->headers->has('X-Debug-Performance');
    }
}
