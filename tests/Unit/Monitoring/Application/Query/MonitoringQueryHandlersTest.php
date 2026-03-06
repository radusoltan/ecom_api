<?php

declare(strict_types=1);

namespace App\Tests\Unit\Monitoring\Application\Query;

use App\Monitoring\Application\Query\GetHealthStatusQuery;
use App\Monitoring\Application\Query\GetHealthStatusQueryHandler;
use App\Monitoring\Application\Query\GetMetricStatisticsQuery;
use App\Monitoring\Application\Query\GetMetricStatisticsQueryHandler;
use App\Monitoring\Application\Query\GetPerformanceStatusQuery;
use App\Monitoring\Application\Query\GetPerformanceStatusQueryHandler;
use App\Monitoring\Application\Service\HealthCheckService;
use App\Monitoring\Infrastructure\Service\ApplicationPerformanceMonitor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetHealthStatusQueryHandler::class)]
#[CoversClass(GetHealthStatusQuery::class)]
#[CoversClass(GetPerformanceStatusQueryHandler::class)]
#[CoversClass(GetPerformanceStatusQuery::class)]
#[CoversClass(GetMetricStatisticsQueryHandler::class)]
#[CoversClass(GetMetricStatisticsQuery::class)]
final class MonitoringQueryHandlersTest extends TestCase
{
    // --- GetHealthStatusQueryHandler ---

    #[Test]
    public function itDelegatesHealthCheckToService(): void
    {
        $expected = [
            'status' => 'pass',
            'checks' => [
                'database' => ['status' => 'pass', 'time' => '5ms', 'output' => ''],
            ],
        ];

        $service = $this->createMock(HealthCheckService::class);
        $service->expects(self::once())
            ->method('check')
            ->willReturn($expected);

        $handler = new GetHealthStatusQueryHandler($service);
        $result = $handler(new GetHealthStatusQuery());

        self::assertSame($expected, $result);
    }

    // --- GetPerformanceStatusQueryHandler ---

    #[Test]
    public function itDelegatesPerformanceStatusToApm(): void
    {
        $expected = [
            'api_response_time' => 120.5,
            'cache_hit_rate' => 92.3,
            'status' => 'healthy',
        ];

        $apm = $this->createMock(ApplicationPerformanceMonitor::class);
        $apm->expects(self::once())
            ->method('getPerformanceStatus')
            ->willReturn($expected);

        $handler = new GetPerformanceStatusQueryHandler($apm);
        $result = $handler(new GetPerformanceStatusQuery());

        self::assertSame($expected, $result);
    }

    // --- GetMetricStatisticsQueryHandler ---

    #[Test]
    public function itDelegatesMetricStatisticsToApm(): void
    {
        $expected = ['avg' => 150.0, 'p95' => 195.0, 'max' => 250.0];

        $apm = $this->createMock(ApplicationPerformanceMonitor::class);
        $apm->expects(self::once())
            ->method('getMetricStatistics')
            ->with('api_response_time', 3600)
            ->willReturn($expected);

        $handler = new GetMetricStatisticsQueryHandler($apm);
        $result = $handler(new GetMetricStatisticsQuery('api_response_time'));

        self::assertSame($expected, $result);
    }

    #[Test]
    public function itPassesCustomPeriodToApm(): void
    {
        $apm = $this->createMock(ApplicationPerformanceMonitor::class);
        $apm->expects(self::once())
            ->method('getMetricStatistics')
            ->with('db_query_time', 86400)
            ->willReturn(null);

        $handler = new GetMetricStatisticsQueryHandler($apm);
        $result = $handler(new GetMetricStatisticsQuery('db_query_time', 86400));

        self::assertNull($result);
    }

    #[Test]
    public function metricStatisticsQueryDefaultPeriod(): void
    {
        $query = new GetMetricStatisticsQuery('cpu_usage');

        self::assertSame('cpu_usage', $query->metric);
        self::assertSame(3600, $query->period);
    }
}
