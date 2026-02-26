<?php

declare(strict_types=1);

namespace App\Tests\Unit\Monitoring\Infrastructure\Http\Middleware;

use App\Monitoring\Infrastructure\Http\Middleware\PerformanceMonitoringMiddleware;
use App\Monitoring\Infrastructure\Service\ApplicationPerformanceMonitor;
use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Infrastructure\Metrics\MetricsCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Unit tests for PerformanceMonitoringMiddleware.
 *
 * ApplicationPerformanceMonitor is `final` and not in the BypassFinals whitelist,
 * so we use a real instance constructed with lightweight collaborators.
 */
#[CoversClass(PerformanceMonitoringMiddleware::class)]
final class PerformanceMonitoringMiddlewareTest extends TestCase
{
    private ApplicationPerformanceMonitor $apm;
    private PerformanceMonitoringMiddleware $middleware;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(fn (string $k, callable $cb) => 85.0);

        $this->apm = new ApplicationPerformanceMonitor(
            metricsCollector: new MetricsCollector('test'),
            performanceProfiler: new PerformanceProfiler(new NullLogger()),
            cache: $cache,
            logger: new NullLogger(),
        );

        $this->middleware = new PerformanceMonitoringMiddleware($this->apm);
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    // -----------------------------------------------------------------------
    // getSubscribedEvents
    // -----------------------------------------------------------------------

    #[Test]
    public function itSubscribesToRequestAndResponseKernelEvents(): void
    {
        $events = PerformanceMonitoringMiddleware::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::REQUEST, $events);
        self::assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    #[Test]
    public function itRegistersOnKernelRequestWithHighPriority(): void
    {
        $events = PerformanceMonitoringMiddleware::getSubscribedEvents();

        [$method, $priority] = $events[KernelEvents::REQUEST];

        self::assertSame('onKernelRequest', $method);
        self::assertGreaterThan(0, $priority);
    }

    #[Test]
    public function itRegistersOnKernelResponseWithLowPriority(): void
    {
        $events = PerformanceMonitoringMiddleware::getSubscribedEvents();

        [$method, $priority] = $events[KernelEvents::RESPONSE];

        self::assertSame('onKernelResponse', $method);
        self::assertLessThan(0, $priority);
    }

    // -----------------------------------------------------------------------
    // onKernelRequest
    // -----------------------------------------------------------------------

    #[Test]
    public function itSetsStartTimeAttributeOnMainRequest(): void
    {
        $request = new Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $before = microtime(true);
        $this->middleware->onKernelRequest($event);
        $after = microtime(true);

        $startTime = $request->attributes->get('_perf_start_time');
        self::assertNotNull($startTime);
        self::assertGreaterThanOrEqual($before, $startTime);
        self::assertLessThanOrEqual($after, $startTime);
    }

    #[Test]
    public function itSetsStartMemoryAttributeOnMainRequest(): void
    {
        $request = new Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->middleware->onKernelRequest($event);

        $startMemory = $request->attributes->get('_perf_start_memory');
        self::assertNotNull($startMemory);
        self::assertIsInt($startMemory);
        self::assertGreaterThan(0, $startMemory);
    }

    #[Test]
    public function itDoesNotSetAttributesForSubRequests(): void
    {
        $request = new Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->middleware->onKernelRequest($event);

        self::assertNull($request->attributes->get('_perf_start_time'));
        self::assertNull($request->attributes->get('_perf_start_memory'));
    }

    // -----------------------------------------------------------------------
    // onKernelResponse — observable effects on the APM state
    // -----------------------------------------------------------------------

    #[Test]
    public function itRecordsApiRequestMetricWhenStartTimeIsPresent(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $request->attributes->set('_perf_start_time', microtime(true) - 0.1);
        $request->attributes->set('_route', 'app_test_route');

        $response = new Response('OK', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        // Before the call there should be no history for this metric.
        self::assertEmpty($this->apm->getPerformanceMetrics('api_response_time'));

        $this->middleware->onKernelResponse($event);

        // After the call at least one entry should exist in history.
        self::assertNotEmpty($this->apm->getPerformanceMetrics('api_response_time'));
    }

    #[Test]
    public function itDoesNotRecordMetricWhenStartTimeAttributeIsMissing(): void
    {
        $request = new Request();
        // Intentionally NOT setting _perf_start_time attribute.

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->middleware->onKernelResponse($event);

        self::assertEmpty($this->apm->getPerformanceMetrics('api_response_time'));
    }

    #[Test]
    public function itDoesNotRecordMetricForSubRequests(): void
    {
        $request = new Request();
        $request->attributes->set('_perf_start_time', microtime(true));

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $this->middleware->onKernelResponse($event);

        self::assertEmpty($this->apm->getPerformanceMetrics('api_response_time'));
    }

    #[Test]
    public function itUsesUnknownRouteWhenRouteAttributeIsAbsent(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $request->attributes->set('_perf_start_time', microtime(true) - 0.01);
        // _route attribute intentionally omitted — middleware should fall back to 'unknown'.

        $response = new Response('', 404);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        // Should not throw; the APM still records the metric.
        $this->middleware->onKernelResponse($event);

        self::assertNotEmpty($this->apm->getPerformanceMetrics('api_response_time'));
    }

    #[Test]
    public function itAddsPerformanceHeadersInDevEnvironment(): void
    {
        // Temporarily force APP_ENV to 'dev' so headers are injected.
        $originalEnv = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'dev';

        try {
            $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
            $request->attributes->set('_perf_start_time', microtime(true) - 0.05);
            $request->attributes->set('_route', 'app_route');

            $response = new Response('', 200);
            $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

            $this->middleware->onKernelResponse($event);

            self::assertTrue($response->headers->has('X-Response-Time'));
            self::assertTrue($response->headers->has('X-Memory-Usage'));
        } finally {
            if (null === $originalEnv) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $originalEnv;
            }
        }
    }

    #[Test]
    public function itAddsPerformanceHeadersWhenDebugHeaderIsPresent(): void
    {
        // Force non-dev so the debug header path is exercised instead.
        $originalEnv = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'test';

        try {
            $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
            $request->headers->set('X-Debug-Performance', '1');
            $request->attributes->set('_perf_start_time', microtime(true) - 0.02);
            $request->attributes->set('_route', 'app_route');

            $response = new Response('', 200);
            $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

            $this->middleware->onKernelResponse($event);

            self::assertTrue($response->headers->has('X-Response-Time'));
            self::assertTrue($response->headers->has('X-Memory-Usage'));
        } finally {
            if (null === $originalEnv) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $originalEnv;
            }
        }
    }
}
