<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\EventListener;

use App\Shared\Infrastructure\Metrics\MetricsCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * API Metrics Listener.
 *
 * Collects API request metrics:
 * - api_requests_total (counter)
 * - api_request_duration_seconds (histogram)
 *
 * Tracks request latency for performance monitoring and SLO compliance.
 * Target: p95 latency < 200ms
 *
 * @see EPIC 3.3 - Observability & Tracing
 */
final class ApiMetricsListener implements EventSubscriberInterface
{
    private const REQUEST_START_TIME_ATTRIBUTE = '_request_start_time';

    public function __construct(
        private readonly MetricsCollector $metricsCollector
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
            KernelEvents::TERMINATE => ['onKernelTerminate', -1024],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Store request start time for duration calculation
        $event->getRequest()->attributes->set(
            self::REQUEST_START_TIME_ATTRIBUTE,
            microtime(true)
        );
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only track API requests
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        // Skip metrics endpoint to avoid recursive collection
        if (str_starts_with($request->getPathInfo(), '/metrics')) {
            return;
        }

        $startTime = $request->attributes->get(self::REQUEST_START_TIME_ATTRIBUTE);
        if (null === $startTime) {
            return;
        }

        $duration = microtime(true) - $startTime;
        $method = $request->getMethod();
        $path = $this->normalizePath($request->getPathInfo());
        $statusCode = $response->getStatusCode();
        $tenantId = $request->headers->get('X-Tenant-ID', 'unknown');

        // Increment request counter
        $this->metricsCollector->incrementCounter(
            'api_requests_total',
            [
                'method' => $method,
                'path' => $path,
                'status' => (string) $statusCode,
                'tenant_id' => $tenantId,
            ]
        );

        // Observe request duration
        $this->metricsCollector->observeHistogram(
            'api_request_duration_seconds',
            $duration,
            [
                'method' => $method,
                'path' => $path,
                'tenant_id' => $tenantId,
            ]
        );
    }

    /**
     * Normalize path to avoid high cardinality.
     *
     * Replaces UUIDs/ULIDs with placeholders
     */
    private function normalizePath(string $path): string
    {
        // Replace UUIDs with {id}
        $path = preg_replace(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            '{id}',
            $path
        );

        // Replace ULIDs with {id}
        $path = preg_replace(
            '/[0-9A-HJKMNP-TV-Z]{26}/i',
            '{id}',
            $path
        );

        return $path;
    }
}
