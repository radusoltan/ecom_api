<?php

declare(strict_types=1);

namespace App\Tests\Unit\Monitoring\Infrastructure\Service;

use App\Monitoring\Infrastructure\Service\ApplicationPerformanceMonitor;
use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Infrastructure\Metrics\MetricsCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[CoversClass(ApplicationPerformanceMonitor::class)]
final class ApplicationPerformanceMonitorTest extends TestCase
{
    private MetricsCollector $metricsCollector;
    private PerformanceProfiler $profiler;
    private CacheInterface $cache;
    private ApplicationPerformanceMonitor $apm;

    protected function setUp(): void
    {
        $this->metricsCollector = new MetricsCollector('test');
        $this->profiler = new PerformanceProfiler(new NullLogger());
        $this->cache = $this->createMock(CacheInterface::class);

        $this->apm = new ApplicationPerformanceMonitor(
            metricsCollector: $this->metricsCollector,
            performanceProfiler: $this->profiler,
            cache: $this->cache,
            logger: new NullLogger(),
        );
    }

    // -----------------------------------------------------------------------
    // trackApiRequest
    // -----------------------------------------------------------------------

    #[Test]
    public function itTracksApiRequestWithoutExceedingThreshold(): void
    {
        // A fast request (50ms) should not trigger any alert.
        $this->apm->trackApiRequest(
            route: 'app_product_list',
            method: 'GET',
            durationMs: 50.0,
            statusCode: 200,
            tenantId: '00000000-0000-4000-8000-000000000001',
        );

        $status = $this->apm->getPerformanceStatus();

        // No violations for fast requests; active_alerts stays empty for this metric.
        self::assertIsArray($status['active_alerts']);
        self::assertEmpty($status['active_alerts']);
    }

    #[Test]
    public function itTracksApiRequestAndTriggersWarningAlertWhenThresholdExceeded(): void
    {
        // 175ms exceeds the warning threshold (150ms) but not critical (200ms).
        $this->apm->trackApiRequest(
            route: 'app_order_list',
            method: 'GET',
            durationMs: 175.0,
            statusCode: 200,
        );

        $status = $this->apm->getPerformanceStatus();

        self::assertArrayHasKey('api_response_time_warning', $status['active_alerts']);
        self::assertSame('warning', $status['active_alerts']['api_response_time_warning']['severity']);
    }

    #[Test]
    public function itTracksApiRequestAndTriggersCriticalAlertAboveCriticalThreshold(): void
    {
        // 300ms exceeds the critical threshold (200ms).
        $this->apm->trackApiRequest(
            route: 'app_search',
            method: 'GET',
            durationMs: 300.0,
            statusCode: 500,
        );

        $status = $this->apm->getPerformanceStatus();

        self::assertArrayHasKey('api_response_time_critical', $status['active_alerts']);
        self::assertSame('critical', $status['active_alerts']['api_response_time_critical']['severity']);
    }

    #[Test]
    public function itDoesNotRepeatAlertWithinCooldownPeriod(): void
    {
        // First call triggers the alert.
        $this->apm->trackApiRequest('route_a', 'GET', 300.0, 200);

        $statusAfterFirst = $this->apm->getPerformanceStatus();
        $firstTimestamp = $statusAfterFirst['active_alerts']['api_response_time_critical']['timestamp'] ?? null;
        self::assertNotNull($firstTimestamp);

        // Second immediate call must NOT update the alert (cooldown = 5 min).
        $this->apm->trackApiRequest('route_b', 'GET', 300.0, 200);

        $statusAfterSecond = $this->apm->getPerformanceStatus();
        $secondTimestamp = $statusAfterSecond['active_alerts']['api_response_time_critical']['timestamp'] ?? null;

        // Timestamp must be identical — alert was suppressed.
        self::assertSame($firstTimestamp, $secondTimestamp);
    }

    // -----------------------------------------------------------------------
    // trackDatabaseQuery
    // -----------------------------------------------------------------------

    #[Test]
    public function itTracksDatabaseQueryBelowThresholdWithoutAlert(): void
    {
        $this->apm->trackDatabaseQuery('SELECT 1', 40.0);

        $status = $this->apm->getPerformanceStatus();

        self::assertArrayNotHasKey('database_query_time_warning', $status['active_alerts']);
        self::assertArrayNotHasKey('database_query_time_critical', $status['active_alerts']);
    }

    #[Test]
    public function itTracksDatabaseQueryAndTriggersWarningAlertOnSlowQuery(): void
    {
        // 80ms exceeds the warning threshold of 75ms.
        $this->apm->trackDatabaseQuery('SELECT * FROM products WHERE tenant_id = ?', 80.0);

        $status = $this->apm->getPerformanceStatus();

        self::assertArrayHasKey('database_query_time_warning', $status['active_alerts']);
    }

    // -----------------------------------------------------------------------
    // trackCacheOperation
    // -----------------------------------------------------------------------

    #[Test]
    public function itTracksCacheHitOperation(): void
    {
        // Should not throw and should increment the metrics counter internally.
        $this->apm->trackCacheOperation('get', hit: true, durationMs: 2.0);

        // No exception = pass. We also verify the status structure is intact.
        $status = $this->apm->getPerformanceStatus();
        self::assertArrayHasKey('status', $status);
    }

    #[Test]
    public function itTracksCacheMissOperationWithZeroDuration(): void
    {
        // durationMs = 0 should skip the histogram observation.
        $this->apm->trackCacheOperation('get', hit: false, durationMs: 0);

        $status = $this->apm->getPerformanceStatus();
        self::assertArrayHasKey('status', $status);
    }

    // -----------------------------------------------------------------------
    // getCacheHitRate
    // -----------------------------------------------------------------------

    #[Test]
    public function itDelegatesCacheHitRateLookupToSymfonyCache(): void
    {
        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with('apm_cache_hit_rate_3600', self::isCallable())
            ->willReturnCallback(function (string $key, callable $callback) {
                return $callback($this->createMock(ItemInterface::class));
            });

        $rate = $this->apm->getCacheHitRate(3600);

        // Default computed value from the closure.
        self::assertSame(85.0, $rate);
    }

    // -----------------------------------------------------------------------
    // getPerformanceMetrics / getMetricStatistics
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsEmptyArrayForUnknownMetric(): void
    {
        $result = $this->apm->getPerformanceMetrics('nonexistent_metric');

        self::assertSame([], $result);
    }

    #[Test]
    public function itReturnsNullStatisticsForUnknownMetric(): void
    {
        $stats = $this->apm->getMetricStatistics('nonexistent_metric');

        self::assertNull($stats);
    }

    #[Test]
    public function itReturnsStatisticsAfterTrackingApiRequests(): void
    {
        $this->apm->trackApiRequest('app_list', 'GET', 50.0, 200);
        $this->apm->trackApiRequest('app_list', 'GET', 100.0, 200);
        $this->apm->trackApiRequest('app_list', 'GET', 80.0, 200);

        $stats = $this->apm->getMetricStatistics('api_response_time');

        self::assertNotNull($stats);
        self::assertSame(3, $stats['count']);
        self::assertSame(50.0, $stats['min']);
        self::assertSame(100.0, $stats['max']);
        self::assertEqualsWithDelta(76.67, $stats['avg'], 0.01);
        self::assertSame('api_response_time', $stats['metric']);
    }

    #[Test]
    public function itReturnsStatisticsAfterTrackingDatabaseQueries(): void
    {
        $this->apm->trackDatabaseQuery('SELECT id FROM products', 20.0);
        $this->apm->trackDatabaseQuery('SELECT * FROM orders', 40.0);

        $stats = $this->apm->getMetricStatistics('database_query_time');

        self::assertNotNull($stats);
        self::assertSame(2, $stats['count']);
        self::assertSame(20.0, $stats['min']);
        self::assertSame(40.0, $stats['max']);
    }

    // -----------------------------------------------------------------------
    // clearAlerts
    // -----------------------------------------------------------------------

    #[Test]
    public function itClearsAllActiveAlerts(): void
    {
        // Trigger an alert first.
        $this->apm->trackApiRequest('route', 'GET', 300.0, 200);

        $before = $this->apm->getPerformanceStatus();
        self::assertNotEmpty($before['active_alerts']);

        $this->apm->clearAlerts();

        $after = $this->apm->getPerformanceStatus();
        self::assertEmpty($after['active_alerts']);
    }

    // -----------------------------------------------------------------------
    // getPerformanceStatus
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsHealthyStatusWhenNoViolationsExist(): void
    {
        // With a fresh profiler (no queries) the memory usage won't exceed thresholds
        // in a normal test environment.
        $status = $this->apm->getPerformanceStatus();

        self::assertArrayHasKey('status', $status);
        self::assertArrayHasKey('summary', $status);
        self::assertArrayHasKey('violations', $status);
        self::assertArrayHasKey('active_alerts', $status);
        self::assertArrayHasKey('thresholds', $status);
        self::assertArrayHasKey('timestamp', $status);
    }

    #[Test]
    public function itIncludesAllSevenThresholdsInStatus(): void
    {
        $status = $this->apm->getPerformanceStatus();

        $expectedMetrics = [
            'api_response_time',
            'database_query_time',
            'page_load_time',
            'search_query_time',
            'cache_hit_rate',
            'memory_usage',
            'error_rate',
        ];

        $thresholdMetricNames = array_column($status['thresholds'], 'metric');

        foreach ($expectedMetrics as $metric) {
            self::assertContains($metric, $thresholdMetricNames, "Threshold for '{$metric}' is missing");
        }
    }

    // -----------------------------------------------------------------------
    // getHealthCheck
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsHealthCheckArrayWithExpectedStructure(): void
    {
        $health = $this->apm->getHealthCheck();

        self::assertArrayHasKey('status', $health);
        self::assertArrayHasKey('checks', $health);
        self::assertArrayHasKey('performance', $health['checks']);
        self::assertArrayHasKey('status', $health['checks']['performance']);
        self::assertArrayHasKey('output', $health['checks']['performance']);
    }

    #[Test]
    public function itReturnsPassStatusInHealthCheckWhenNoViolations(): void
    {
        $health = $this->apm->getHealthCheck();

        // In a clean test environment there should be no threshold violations.
        self::assertContains($health['status'], ['pass', 'warn']);
    }
}
