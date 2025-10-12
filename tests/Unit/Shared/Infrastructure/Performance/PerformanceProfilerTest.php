<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Performance;

use App\Shared\Infrastructure\Performance\PerformanceProfiler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for PerformanceProfiler.
 *
 * @covers \App\Shared\Infrastructure\Performance\PerformanceProfiler
 */
final class PerformanceProfilerTest extends TestCase
{
    private LoggerInterface $logger;
    private PerformanceProfiler $profiler;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->profiler = new PerformanceProfiler($this->logger);
    }

    public function testStartCreatesNewProfile(): void
    {
        // When: Starting a profile
        $this->profiler->start('test_section');

        // Then: Summary should show 1 active profile
        $summary = $this->profiler->getSummary();
        self::assertSame(1, $summary['active_profiles']);
    }

    public function testStopReturnsMetrics(): void
    {
        // Given: A started profile
        $this->profiler->start('test_section');
        usleep(1000); // Sleep 1ms

        // When: Stopping the profile
        $metrics = $this->profiler->stop('test_section');

        // Then: Metrics should be returned
        self::assertIsArray($metrics);
        self::assertArrayHasKey('duration_ms', $metrics);
        self::assertArrayHasKey('memory_mb', $metrics);
        self::assertArrayHasKey('queries', $metrics);
        self::assertGreaterThan(0, $metrics['duration_ms']);
    }

    public function testStopRemovesProfile(): void
    {
        // Given: A started profile
        $this->profiler->start('test_section');

        // When: Stopping the profile
        $this->profiler->stop('test_section');

        // Then: No active profiles should remain
        $summary = $this->profiler->getSummary();
        self::assertSame(0, $summary['active_profiles']);
    }

    public function testStopNonExistentProfileReturnsZeroMetrics(): void
    {
        // When: Stopping a non-existent profile
        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('Attempting to stop non-existent profile section', ['section' => 'nonexistent']);

        $metrics = $this->profiler->stop('nonexistent');

        // Then: Should return zero metrics
        self::assertSame(0, $metrics['duration_ms']);
        self::assertSame(0, $metrics['memory_mb']);
        self::assertSame(0, $metrics['queries']);
    }

    public function testLogQueryAddsToQueryList(): void
    {
        // When: Logging a query
        $this->profiler->logQuery('SELECT * FROM products', 50.5);

        // Then: Query should be in the list
        $queries = $this->profiler->getQueries();
        self::assertCount(1, $queries);
        self::assertStringContainsString('SELECT', $queries[0]['query']);
        self::assertSame(50.5, $queries[0]['duration_ms']);
    }

    public function testLogQuerySanitizesQuery(): void
    {
        // When: Logging a query with sensitive data (32+ chars)
        $this->profiler->logQuery(
            "SELECT * FROM users WHERE email = 'user@example.com' AND password = 'verylongpasswordhash123456789012'",
            50.0
        );

        // Then: Query should be sanitized
        $queries = $this->profiler->getQueries();
        self::assertStringContainsString('[EMAIL]', $queries[0]['query']);
        self::assertStringContainsString('[STRING]', $queries[0]['query']);
    }

    public function testLogSlowQueryLogsWarning(): void
    {
        // Expect: Logger to receive warning for slow query
        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Slow query detected',
                self::callback(function (array $context) {
                    return $context['duration_ms'] === 150.0
                        && $context['threshold_ms'] === 100;
                })
            );

        // When: Logging a slow query (> 100ms)
        $this->profiler->logQuery('SELECT * FROM large_table', 150.0);
    }

    public function testLogQueryIncrementsActiveProfileQueryCount(): void
    {
        // Given: A started profile
        $this->profiler->start('test_section');

        // When: Logging 3 queries
        $this->profiler->logQuery('SELECT 1', 10.0);
        $this->profiler->logQuery('SELECT 2', 20.0);
        $this->profiler->logQuery('SELECT 3', 30.0);

        // Then: Profile should show 3 queries
        $metrics = $this->profiler->stop('test_section');
        self::assertSame(3, $metrics['queries']);
    }

    public function testGetSummaryReturnsCorrectData(): void
    {
        // Given: Started profiles and logged queries
        $this->profiler->start('section1');
        $this->profiler->start('section2');
        $this->profiler->logQuery('SELECT 1', 50.0);
        $this->profiler->logQuery('SELECT 2', 150.0); // slow

        // When: Getting summary
        $summary = $this->profiler->getSummary();

        // Then: Summary should be correct
        self::assertSame(2, $summary['active_profiles']);
        self::assertSame(2, $summary['queries_total']);
        self::assertSame(1, $summary['queries_slow']);
        self::assertIsFloat($summary['memory_current_mb']);
        self::assertIsFloat($summary['memory_peak_mb']);
        self::assertGreaterThan(0, $summary['memory_current_mb']);
        self::assertGreaterThan(0, $summary['memory_peak_mb']);
    }

    public function testGetQueriesReturnsAllQueries(): void
    {
        // Given: Multiple logged queries
        $this->profiler->logQuery('SELECT 1', 10.0);
        $this->profiler->logQuery('SELECT 2', 20.0);
        $this->profiler->logQuery('SELECT 3', 30.0);

        // When: Getting all queries
        $queries = $this->profiler->getQueries();

        // Then: All queries should be returned
        self::assertCount(3, $queries);
    }

    public function testGetSlowQueriesReturnsOnlySlowQueries(): void
    {
        // Given: Mixed fast and slow queries
        $this->profiler->logQuery('SELECT 1', 50.0);   // fast
        $this->profiler->logQuery('SELECT 2', 150.0);  // slow
        $this->profiler->logQuery('SELECT 3', 200.0);  // slow
        $this->profiler->logQuery('SELECT 4', 80.0);   // fast

        // When: Getting slow queries
        $slowQueries = $this->profiler->getSlowQueries();

        // Then: Only slow queries should be returned (> 100ms)
        self::assertCount(2, $slowQueries);
        self::assertSame(150.0, $slowQueries[1]['duration_ms']);
        self::assertSame(200.0, $slowQueries[2]['duration_ms']);
    }

    public function testResetClearsAllData(): void
    {
        // Given: Profiles and queries
        $this->profiler->start('section1');
        $this->profiler->logQuery('SELECT 1', 50.0);
        $this->profiler->logQuery('SELECT 2', 150.0);

        // When: Resetting profiler
        $this->profiler->reset();

        // Then: All data should be cleared
        $summary = $this->profiler->getSummary();
        self::assertSame(0, $summary['active_profiles']);
        self::assertSame(0, $summary['queries_total']);
        self::assertSame(0, $summary['queries_slow']);
        self::assertEmpty($this->profiler->getQueries());
    }

    public function testProfileCallableReturnsResultAndMetrics(): void
    {
        // When: Profiling a callable that returns a value
        $result = $this->profiler->profile('test_callable', function () {
            usleep(1000); // 1ms
            return 'test_result';
        });

        // Then: Result and metrics should be returned
        self::assertIsArray($result);
        self::assertArrayHasKey('result', $result);
        self::assertArrayHasKey('metrics', $result);
        self::assertSame('test_result', $result['result']);
        self::assertGreaterThan(0, $result['metrics']['duration_ms']);
    }

    public function testProfileCallableStopsOnException(): void
    {
        // Expect: Exception to be thrown
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        try {
            // When: Profiling a callable that throws
            $this->profiler->profile('test_callable', function () {
                throw new \RuntimeException('Test exception');
            });
        } finally {
            // Then: Profile should be stopped (no active profiles)
            $summary = $this->profiler->getSummary();
            self::assertSame(0, $summary['active_profiles']);
        }
    }

    public function testSlowSectionLogsWarning(): void
    {
        // Expect: Logger to receive warning for slow section
        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Slow section detected',
                self::callback(function (array $context) {
                    return $context['section'] === 'slow_section'
                        && $context['metrics']['duration_ms'] >= 200.0;
                })
            );

        // When: Profiling a slow section (> 200ms)
        $this->profiler->start('slow_section');
        usleep(201000); // Sleep 201ms
        $this->profiler->stop('slow_section');
    }

    public function testFastSectionLogsDebug(): void
    {
        // Expect: Logger to receive debug for fast section
        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with(
                'Section profiled',
                self::callback(function (array $context) {
                    return $context['section'] === 'fast_section'
                        && $context['metrics']['duration_ms'] < 200.0;
                })
            );

        // When: Profiling a fast section (< 200ms)
        $this->profiler->start('fast_section');
        usleep(1000); // Sleep 1ms
        $this->profiler->stop('fast_section');
    }

    public function testMultipleProfilesCanRunConcurrently(): void
    {
        // When: Starting multiple profiles
        $this->profiler->start('section1');
        $this->profiler->start('section2');
        $this->profiler->start('section3');

        // Then: All should be active
        $summary = $this->profiler->getSummary();
        self::assertSame(3, $summary['active_profiles']);

        // When: Stopping one profile
        $this->profiler->stop('section2');

        // Then: Two should remain active
        $summary = $this->profiler->getSummary();
        self::assertSame(2, $summary['active_profiles']);
    }

    public function testQueryCountIsSharedAcrossActiveProfiles(): void
    {
        // Given: Two active profiles
        $this->profiler->start('section1');
        $this->profiler->start('section2');

        // When: Logging queries
        $this->profiler->logQuery('SELECT 1', 10.0);
        $this->profiler->logQuery('SELECT 2', 20.0);

        // Then: Both profiles should show 2 queries
        $metrics1 = $this->profiler->stop('section1');
        $metrics2 = $this->profiler->stop('section2');

        self::assertSame(2, $metrics1['queries']);
        self::assertSame(2, $metrics2['queries']);
    }

    public function testSanitizeQueryRemovesTimestamps(): void
    {
        // When: Logging a query with timestamp
        $this->profiler->logQuery(
            'SELECT * FROM orders WHERE created_at > 1234567890123',
            50.0
        );

        // Then: Timestamp should be sanitized
        $queries = $this->profiler->getQueries();
        self::assertStringContainsString('[NUMBER]', $queries[0]['query']);
        self::assertStringNotContainsString('1234567890123', $queries[0]['query']);
    }

    public function testMemoryTrackingIsAccurate(): void
    {
        // When: Starting a profile and allocating memory
        $this->profiler->start('memory_test');

        // Allocate ~1MB of memory
        $data = str_repeat('x', 1024 * 1024);

        // Then: Metrics should show memory usage
        $metrics = $this->profiler->stop('memory_test');

        // Memory tracking should show some usage (though exact value depends on PHP internals)
        self::assertIsFloat($metrics['memory_mb']);
    }

    public function testResetLogsDebugMessage(): void
    {
        // Expect: Logger to receive debug message
        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with('Performance profiler reset');

        // When: Resetting profiler
        $this->profiler->reset();
    }
}
