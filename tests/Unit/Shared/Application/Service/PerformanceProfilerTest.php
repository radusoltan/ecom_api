<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Service;

use App\Shared\Application\Service\PerformanceProfiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(PerformanceProfiler::class)]
final class PerformanceProfilerTest extends TestCase
{
    private LoggerInterface $logger;
    private PerformanceProfiler $profiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->profiler = new PerformanceProfiler($this->logger);
    }

    // -----------------------------------------------------------------------
    // start / stop
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsMetricsOnStop(): void
    {
        $this->profiler->start('test_section');
        $metrics = $this->profiler->stop('test_section');

        self::assertArrayHasKey('duration_ms', $metrics);
        self::assertArrayHasKey('memory_mb', $metrics);
        self::assertArrayHasKey('queries', $metrics);
    }

    #[Test]
    public function itReturnsZeroQueriesWhenNoneLogged(): void
    {
        $this->profiler->start('section');
        $metrics = $this->profiler->stop('section');

        self::assertSame(0, $metrics['queries']);
    }

    #[Test]
    public function itReturnsZeroMetricsForNonExistentSection(): void
    {
        $metrics = $this->profiler->stop('ghost_section');

        self::assertSame(0, $metrics['duration_ms']);
        self::assertSame(0, $metrics['memory_mb']);
        self::assertSame(0, $metrics['queries']);
    }

    #[Test]
    public function itLogsWarningWhenStoppingNonExistentSection(): void
    {
        $this->logger->expects(self::once())
            ->method('warning')
            ->with('Attempting to stop non-existent profile section', self::anything());

        $this->profiler->stop('non_existent');
    }

    #[Test]
    public function itClearsProfileAfterStop(): void
    {
        $this->profiler->start('cleared_section');
        $this->profiler->stop('cleared_section');

        // Second stop should return zeroes (section already gone)
        $metrics = $this->profiler->stop('cleared_section');
        self::assertSame(0, $metrics['duration_ms']);
    }

    // -----------------------------------------------------------------------
    // logQuery
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsQueryAndItAppearsInGetQueries(): void
    {
        $this->profiler->logQuery('SELECT 1', 10.0);

        self::assertCount(1, $this->profiler->getQueries());
    }

    #[Test]
    public function itSanitizesEmailsInQuery(): void
    {
        $this->profiler->logQuery("SELECT * FROM users WHERE email = 'test@example.com'", 5.0);

        $queries = $this->profiler->getQueries();
        self::assertStringNotContainsString('test@example.com', $queries[0]['query']);
        self::assertStringContainsString('[EMAIL]', $queries[0]['query']);
    }

    #[Test]
    public function itLogsWarningForSlowQuery(): void
    {
        $this->logger->expects(self::once())
            ->method('warning')
            ->with('Slow query detected', self::anything());

        $this->profiler->logQuery('SELECT * FROM big_table', 150.0);
    }

    #[Test]
    public function itDoesNotLogWarningForFastQuery(): void
    {
        $this->logger->expects(self::never())->method('warning');

        $this->profiler->logQuery('SELECT 1', 50.0);
    }

    #[Test]
    public function itIncrementsQueryCountOnActiveProfile(): void
    {
        $this->profiler->start('with_queries');
        $this->profiler->logQuery('SELECT 1', 5.0);
        $metrics = $this->profiler->stop('with_queries');

        self::assertSame(1, $metrics['queries']);
    }

    // -----------------------------------------------------------------------
    // getSlowQueries
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsOnlySlowQueries(): void
    {
        $this->profiler->logQuery('SELECT fast', 20.0);
        $this->profiler->logQuery('SELECT slow', 200.0);

        $slowQueries = $this->profiler->getSlowQueries();
        self::assertCount(1, $slowQueries);
    }

    // -----------------------------------------------------------------------
    // getSummary
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsSummaryWithCorrectKeys(): void
    {
        $summary = $this->profiler->getSummary();

        self::assertArrayHasKey('memory_current_mb', $summary);
        self::assertArrayHasKey('memory_peak_mb', $summary);
        self::assertArrayHasKey('queries_total', $summary);
        self::assertArrayHasKey('queries_slow', $summary);
        self::assertArrayHasKey('active_profiles', $summary);
    }

    #[Test]
    public function itCountsActiveProfilesInSummary(): void
    {
        $this->profiler->start('active_one');
        $this->profiler->start('active_two');

        $summary = $this->profiler->getSummary();
        self::assertSame(2, $summary['active_profiles']);
    }

    // -----------------------------------------------------------------------
    // reset
    // -----------------------------------------------------------------------

    #[Test]
    public function itClearsAllDataOnReset(): void
    {
        $this->profiler->start('to_reset');
        $this->profiler->logQuery('SELECT 1', 5.0);

        $this->profiler->reset();

        $summary = $this->profiler->getSummary();
        self::assertSame(0, $summary['queries_total']);
        self::assertSame(0, $summary['active_profiles']);
    }

    // -----------------------------------------------------------------------
    // profile callable
    // -----------------------------------------------------------------------

    #[Test]
    public function itProfilesCallableAndReturnsResult(): void
    {
        $result = $this->profiler->profile('my_section', fn () => 'hello');

        self::assertSame('hello', $result['result']);
        self::assertArrayHasKey('metrics', $result);
    }

    #[Test]
    public function itRethrowsExceptionFromCallable(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->profiler->profile('fail_section', function (): never {
            throw new \RuntimeException('boom');
        });
    }
}
