<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Application\Service\PerformanceProfiler;
use Doctrine\DBAL\Logging\SQLLogger;

/**
 * Doctrine Query Logger with Performance Profiling.
 *
 * Integrates Doctrine DBAL with PerformanceProfiler to track:
 * - Query execution times
 * - Slow query detection
 * - Query count per request
 * - Memory usage per query
 *
 * Business Rules (PRD Section 9.1):
 * - Query time < 100ms target
 * - Slow query logging threshold: 100ms
 * - Performance monitoring enabled in all environments
 */
final class PerformanceQueryLogger implements SQLLogger
{
    private const SLOW_QUERY_THRESHOLD_MS = 100;

    private ?float $startTime = null;
    private ?string $currentQuery = null;

    public function __construct(
        private readonly PerformanceProfiler $profiler
    ) {
    }

    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        $this->startTime = microtime(true);
        $this->currentQuery = $this->formatQuery($sql, $params);
    }

    public function stopQuery(): void
    {
        if (null === $this->startTime) {
            return;
        }

        $duration = (microtime(true) - $this->startTime) * 1000;

        // Log to profiler
        $this->profiler->logQuery(
            $this->currentQuery ?? 'Unknown query',
            $duration
        );

        // Reset
        $this->startTime = null;
        $this->currentQuery = null;
    }

    /**
     * Format query for logging (sanitize sensitive data).
     */
    private function formatQuery(string $sql, ?array $params = null): string
    {
        // Keep SQL as-is for now (PerformanceProfiler handles sanitization)
        // In future, we could interpolate params here if needed

        return $sql;
    }
}
