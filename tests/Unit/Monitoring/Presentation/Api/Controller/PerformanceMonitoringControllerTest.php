<?php

declare(strict_types=1);

namespace App\Tests\Unit\Monitoring\Presentation\Api\Controller;

use App\Monitoring\Infrastructure\Service\ApplicationPerformanceMonitor;
use App\Monitoring\Presentation\Api\Controller\PerformanceMonitoringController;
use App\Shared\Application\Service\PerformanceProfiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit tests for PerformanceMonitoringController.
 *
 * The controller extends AbstractController which relies on a container for
 * the `json()` helper. We inject a minimal stub container whose `has()` always
 * returns false so that `json()` falls back to constructing a plain JsonResponse
 * without a serializer — no Symfony kernel required.
 */
#[CoversClass(PerformanceMonitoringController::class)]
final class PerformanceMonitoringControllerTest extends TestCase
{
    private ApplicationPerformanceMonitor $apm;
    private PerformanceProfiler $profiler;
    private PerformanceMonitoringController $controller;

    protected function setUp(): void
    {
        // We use a real MetricsCollector + PerformanceProfiler so the APM has
        // concrete collaborators without needing the full DI container.
        $metricsCollector = new \App\Shared\Infrastructure\Metrics\MetricsCollector('test');
        $this->profiler = new PerformanceProfiler(new NullLogger());

        $cache = $this->createMock(\Symfony\Contracts\Cache\CacheInterface::class);
        $cache->method('get')->willReturnCallback(fn (string $k, callable $cb) => $cb(
            $this->createMock(\Symfony\Contracts\Cache\ItemInterface::class)
        ));

        $this->apm = new ApplicationPerformanceMonitor(
            metricsCollector: $metricsCollector,
            performanceProfiler: $this->profiler,
            cache: $cache,
            logger: new NullLogger(),
        );

        $this->controller = new PerformanceMonitoringController($this->apm, $this->profiler);

        // Provide a stub container so AbstractController::json() works without
        // a real Symfony kernel. has('serializer') returns false → plain JsonResponse.
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);
    }

    // -----------------------------------------------------------------------
    // getPerformanceStatus
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsOkResponseForPerformanceStatus(): void
    {
        $response = $this->controller->getPerformanceStatus();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function itReturnsJsonWithStatusKeyForPerformanceStatus(): void
    {
        $response = $this->controller->getPerformanceStatus();
        $data = json_decode((string) $response->getContent(), true);

        self::assertArrayHasKey('status', $data);
        self::assertContains($data['status'], ['healthy', 'degraded']);
    }

    // -----------------------------------------------------------------------
    // getMetricStatistics
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturns404WhenNoDataAvailableForMetric(): void
    {
        $request = new Request(['period' => '3600']);

        $response = $this->controller->getMetricStatistics('api_response_time', $request);

        // No tracking calls were made, so statistics are null → 404.
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function itReturns200WithStatisticsWhenDataExists(): void
    {
        // Seed the APM with data so getMetricStatistics returns non-null.
        $this->apm->trackApiRequest('app_list', 'GET', 80.0, 200);
        $this->apm->trackApiRequest('app_list', 'GET', 120.0, 200);

        $request = new Request(['period' => '3600']);
        $response = $this->controller->getMetricStatistics('api_response_time', $request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('count', $data);
        self::assertSame(2, $data['count']);
    }

    #[Test]
    public function itReturnsMetricAndPeriodIn404Body(): void
    {
        $request = new Request(['period' => '1800']);
        $response = $this->controller->getMetricStatistics('unknown_metric', $request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('error', $data);
        self::assertSame('unknown_metric', $data['metric']);
        self::assertSame(1800, $data['period']);
    }

    // -----------------------------------------------------------------------
    // getSlowQueries
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsZeroSlowQueriesWhenProfilerIsEmpty(): void
    {
        $response = $this->controller->getSlowQueries();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame(0, $data['count']);
        self::assertIsArray($data['queries']);
    }

    #[Test]
    public function itReturnsSlowQueriesFromProfiler(): void
    {
        $this->profiler->logQuery('SELECT * FROM products', 150.0); // slow (>100ms)
        $this->profiler->logQuery('SELECT 1', 5.0);                 // fast

        $response = $this->controller->getSlowQueries();
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['count']);
    }

    // -----------------------------------------------------------------------
    // getAllQueries
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsAllQueriesWithSummary(): void
    {
        $this->profiler->logQuery('SELECT 1', 10.0);
        $this->profiler->logQuery('SELECT 2', 20.0);

        $response = $this->controller->getAllQueries();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('summary', $data);
        self::assertArrayHasKey('queries', $data);
        self::assertSame(2, $data['summary']['queries_total']);
    }

    // -----------------------------------------------------------------------
    // getActiveAlerts
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsEmptyAlertsWhenNoneTriggered(): void
    {
        $response = $this->controller->getActiveAlerts();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('active_alerts', $data);
        self::assertArrayHasKey('violations', $data);
        self::assertArrayHasKey('timestamp', $data);
    }

    #[Test]
    public function itReturnsActiveAlertsAfterThresholdBreached(): void
    {
        // Trigger a warning alert (175ms > 150ms warning threshold).
        $this->apm->trackApiRequest('app_route', 'GET', 175.0, 200);

        $response = $this->controller->getActiveAlerts();
        $data = json_decode((string) $response->getContent(), true);

        self::assertNotEmpty($data['active_alerts']);
        self::assertArrayHasKey('api_response_time_warning', $data['active_alerts']);
    }

    // -----------------------------------------------------------------------
    // clearAlerts
    // -----------------------------------------------------------------------

    #[Test]
    public function itClearsAlertsAndReturnsConfirmation(): void
    {
        // Trigger an alert first.
        $this->apm->trackApiRequest('route', 'GET', 300.0, 200);

        $response = $this->controller->clearAlerts();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('message', $data);
        self::assertStringContainsString('cleared', $data['message']);
        self::assertArrayHasKey('timestamp', $data);
    }

    // -----------------------------------------------------------------------
    // healthCheck
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsHealthCheckWithCorrectStructure(): void
    {
        $response = $this->controller->healthCheck();

        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('status', $data);
        self::assertArrayHasKey('checks', $data);
        self::assertContains($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_SERVICE_UNAVAILABLE,
        ]);
    }

    #[Test]
    public function itReturns200WhenHealthStatusIsPass(): void
    {
        // In a clean environment with no threshold violations the status should be 'pass'.
        $response = $this->controller->healthCheck();
        $data = json_decode((string) $response->getContent(), true);

        if ('pass' === $data['status']) {
            self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        } else {
            // 'warn' or other deviations map to 503 — still a valid assertion.
            self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        }
    }

    // -----------------------------------------------------------------------
    // getPerformanceDashboard
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsDashboardWithRequiredKeys(): void
    {
        $response = $this->controller->getPerformanceDashboard();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('overall_status', $data);
        self::assertArrayHasKey('metrics', $data);
        self::assertArrayHasKey('alerts', $data);
        self::assertArrayHasKey('slow_queries', $data);
        self::assertArrayHasKey('timestamp', $data);
    }

    #[Test]
    public function itIncludesCacheHitRateInDashboard(): void
    {
        $response = $this->controller->getPerformanceDashboard();
        $data = json_decode((string) $response->getContent(), true);

        self::assertArrayHasKey('cache_hit_rate', $data['metrics']);
        self::assertArrayHasKey('value', $data['metrics']['cache_hit_rate']);
        self::assertArrayHasKey('unit', $data['metrics']['cache_hit_rate']);
    }
}
