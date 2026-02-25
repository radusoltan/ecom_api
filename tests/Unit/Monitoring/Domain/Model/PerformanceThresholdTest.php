<?php

declare(strict_types=1);

namespace App\Tests\Unit\Monitoring\Domain\Model;

use App\Monitoring\Domain\Model\PerformanceThreshold;
use App\Monitoring\Domain\ValueObject\AlertSeverity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PerformanceThreshold::class)]
final class PerformanceThresholdTest extends TestCase
{
    #[Test]
    public function apiResponseTimeFactoryMethod(): void
    {
        $threshold = PerformanceThreshold::apiResponseTime();

        self::assertSame('api_response_time', $threshold->metricName);
        self::assertSame(150.0, $threshold->warningThreshold);
        self::assertSame(200.0, $threshold->criticalThreshold);
        self::assertSame('ms', $threshold->unit);
    }

    #[Test]
    public function databaseQueryTimeFactoryMethod(): void
    {
        $threshold = PerformanceThreshold::databaseQueryTime();

        self::assertSame('database_query_time', $threshold->metricName);
        self::assertSame(75.0, $threshold->warningThreshold);
        self::assertSame(100.0, $threshold->criticalThreshold);
    }

    #[Test]
    public function pageLoadTimeFactoryMethod(): void
    {
        $threshold = PerformanceThreshold::pageLoadTime();

        self::assertSame('page_load_time', $threshold->metricName);
        self::assertSame(1500.0, $threshold->warningThreshold);
        self::assertSame(2000.0, $threshold->criticalThreshold);
    }

    #[Test]
    public function searchQueryTimeFactoryMethod(): void
    {
        $threshold = PerformanceThreshold::searchQueryTime();

        self::assertSame('search_query_time', $threshold->metricName);
        self::assertSame(75.0, $threshold->warningThreshold);
        self::assertSame(100.0, $threshold->criticalThreshold);
    }

    #[Test]
    public function cacheHitRateFactoryMethod(): void
    {
        $threshold = PerformanceThreshold::cacheHitRate();

        self::assertSame('cache_hit_rate', $threshold->metricName);
        self::assertSame(80.0, $threshold->warningThreshold);
        self::assertSame(70.0, $threshold->criticalThreshold);
        self::assertSame('%', $threshold->unit);
    }

    #[Test]
    public function memoryUsageFactoryMethod(): void
    {
        $threshold = PerformanceThreshold::memoryUsage();

        self::assertSame('memory_usage', $threshold->metricName);
        self::assertSame(75.0, $threshold->warningThreshold);
        self::assertSame(90.0, $threshold->criticalThreshold);
    }

    #[Test]
    public function errorRateFactoryMethod(): void
    {
        $threshold = PerformanceThreshold::errorRate();

        self::assertSame('error_rate', $threshold->metricName);
        self::assertSame(1.0, $threshold->warningThreshold);
        self::assertSame(5.0, $threshold->criticalThreshold);
    }

    #[Test]
    public function evaluateReturnsNullWhenBelowWarning(): void
    {
        $threshold = PerformanceThreshold::apiResponseTime();

        self::assertNull($threshold->evaluate(100.0));
    }

    #[Test]
    public function evaluateReturnsWarningWhenAtWarningThreshold(): void
    {
        $threshold = PerformanceThreshold::apiResponseTime();

        $result = $threshold->evaluate(150.0);

        self::assertNotNull($result);
        self::assertTrue($result->equals(AlertSeverity::warning()));
    }

    #[Test]
    public function evaluateReturnsWarningBetweenThresholds(): void
    {
        $threshold = PerformanceThreshold::apiResponseTime();

        $result = $threshold->evaluate(175.0);

        self::assertNotNull($result);
        self::assertTrue($result->equals(AlertSeverity::warning()));
    }

    #[Test]
    public function evaluateReturnsCriticalAtCriticalThreshold(): void
    {
        $threshold = PerformanceThreshold::apiResponseTime();

        $result = $threshold->evaluate(200.0);

        self::assertNotNull($result);
        self::assertTrue($result->equals(AlertSeverity::critical()));
    }

    #[Test]
    public function evaluateReturnsCriticalAboveCriticalThreshold(): void
    {
        $threshold = PerformanceThreshold::apiResponseTime();

        $result = $threshold->evaluate(500.0);

        self::assertNotNull($result);
        self::assertTrue($result->equals(AlertSeverity::critical()));
    }

    #[Test]
    public function evaluateCacheHitRateInvertedLogicNullWhenAboveWarning(): void
    {
        $threshold = PerformanceThreshold::cacheHitRate();

        // 90% cache hit rate = healthy (above warning of 80%)
        self::assertNull($threshold->evaluate(90.0));
    }

    #[Test]
    public function evaluateCacheHitRateWarningWhenAtWarningThreshold(): void
    {
        $threshold = PerformanceThreshold::cacheHitRate();

        // 80% = at warning threshold (lower is worse)
        $result = $threshold->evaluate(80.0);

        self::assertNotNull($result);
        self::assertTrue($result->equals(AlertSeverity::warning()));
    }

    #[Test]
    public function evaluateCacheHitRateWarningBetweenThresholds(): void
    {
        $threshold = PerformanceThreshold::cacheHitRate();

        // 75% = between warning (80) and critical (70)
        $result = $threshold->evaluate(75.0);

        self::assertNotNull($result);
        self::assertTrue($result->equals(AlertSeverity::warning()));
    }

    #[Test]
    public function evaluateCacheHitRateCriticalAtCriticalThreshold(): void
    {
        $threshold = PerformanceThreshold::cacheHitRate();

        // 70% = at critical threshold
        $result = $threshold->evaluate(70.0);

        self::assertNotNull($result);
        self::assertTrue($result->equals(AlertSeverity::critical()));
    }

    #[Test]
    public function evaluateCacheHitRateCriticalBelowCriticalThreshold(): void
    {
        $threshold = PerformanceThreshold::cacheHitRate();

        // 50% = well below critical
        $result = $threshold->evaluate(50.0);

        self::assertNotNull($result);
        self::assertTrue($result->equals(AlertSeverity::critical()));
    }

    #[Test]
    public function isViolatedReturnsTrueWhenThresholdExceeded(): void
    {
        $threshold = PerformanceThreshold::apiResponseTime();

        self::assertTrue($threshold->isViolated(200.0));
        self::assertTrue($threshold->isViolated(150.0));
    }

    #[Test]
    public function isViolatedReturnsFalseWhenBelowThreshold(): void
    {
        $threshold = PerformanceThreshold::apiResponseTime();

        self::assertFalse($threshold->isViolated(100.0));
    }

    #[Test]
    public function isViolatedForCacheHitRateLowValue(): void
    {
        $threshold = PerformanceThreshold::cacheHitRate();

        // Low cache hit rate = violation
        self::assertTrue($threshold->isViolated(60.0));
        // High cache hit rate = no violation
        self::assertFalse($threshold->isViolated(95.0));
    }

    #[Test]
    public function customThresholdCanBeCreated(): void
    {
        $threshold = new PerformanceThreshold(
            metricName: 'custom_metric',
            warningThreshold: 50.0,
            criticalThreshold: 80.0,
            unit: 'count',
            description: 'A custom metric'
        );

        self::assertSame('custom_metric', $threshold->metricName);
        self::assertSame('count', $threshold->unit);
        self::assertSame('A custom metric', $threshold->description);
        self::assertNull($threshold->evaluate(40.0));
        self::assertTrue($threshold->isViolated(80.0));
    }
}
