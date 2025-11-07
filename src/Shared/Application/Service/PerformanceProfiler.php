<?php

declare(strict_types=1);

namespace App\Shared\Application\Service;

use Psr\Log\LoggerInterface;

/**
 * Performance profiler for monitoring application performance.
 *
 * Features:
 * - Query execution time tracking
 * - Memory usage monitoring
 * - Slow query detection
 * - Performance bottleneck identification
 * - Real-time performance metrics
 *
 * Business Rules (PRD Section 9.1):
 * - API response < 200ms target
 * - Query time < 100ms target
 * - Memory limit monitoring
 * - Slow query logging
 */
final class PerformanceProfiler
{
    private const SLOW_QUERY_THRESHOLD_MS = 100;
    private const SLOW_API_THRESHOLD_MS = 200;

    /** @var array<string, array{start: float, queries: int, memory_start: int}> */
    private array $profiles = [];

    /** @var array<array{query: string, duration_ms: float, timestamp: float}> */
    private array $queries = [];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Start profiling a section of code.
     */
    public function start(string $section): void
    {
        $this->profiles[$section] = [
            'start' => microtime(true),
            'queries' => 0,
            'memory_start' => memory_get_usage(true),
        ];
    }

    /**
     * Stop profiling and return metrics.
     *
     * @return array{duration_ms: float, memory_mb: float, queries: int}
     */
    public function stop(string $section): array
    {
        if (!isset($this->profiles[$section])) {
            $this->logger->warning('Attempting to stop non-existent profile section', [
                'section' => $section,
            ]);

            return [
                'duration_ms' => 0,
                'memory_mb' => 0,
                'queries' => 0,
            ];
        }

        $profile = $this->profiles[$section];
        $duration = (microtime(true) - $profile['start']) * 1000;
        $memory = (memory_get_usage(true) - $profile['memory_start']) / 1024 / 1024;

        $metrics = [
            'duration_ms' => round($duration, 2),
            'memory_mb' => round($memory, 2),
            'queries' => $profile['queries'],
        ];

        // Log slow sections
        if ($duration > self::SLOW_API_THRESHOLD_MS) {
            $this->logger->warning('Slow section detected', [
                'section' => $section,
                'metrics' => $metrics,
            ]);
        } else {
            $this->logger->debug('Section profiled', [
                'section' => $section,
                'metrics' => $metrics,
            ]);
        }

        unset($this->profiles[$section]);

        return $metrics;
    }

    /**
     * Log a database query.
     */
    public function logQuery(string $query, float $durationMs): void
    {
        $this->queries[] = [
            'query' => $this->sanitizeQuery($query),
            'duration_ms' => round($durationMs, 2),
            'timestamp' => microtime(true),
        ];

        // Log slow queries
        if ($durationMs > self::SLOW_QUERY_THRESHOLD_MS) {
            $this->logger->warning('Slow query detected', [
                'query' => $this->sanitizeQuery($query),
                'duration_ms' => round($durationMs, 2),
                'threshold_ms' => self::SLOW_QUERY_THRESHOLD_MS,
            ]);
        }

        // Increment query count for active profiles
        foreach ($this->profiles as &$profile) {
            ++$profile['queries'];
        }
    }

    /**
     * Get current performance summary.
     *
     * @return array{
     *     memory_current_mb: float,
     *     memory_peak_mb: float,
     *     queries_total: int,
     *     queries_slow: int,
     *     active_profiles: int
     * }
     */
    public function getSummary(): array
    {
        $slowQueries = array_filter(
            $this->queries,
            fn ($q) => $q['duration_ms'] > self::SLOW_QUERY_THRESHOLD_MS
        );

        return [
            'memory_current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'queries_total' => count($this->queries),
            'queries_slow' => count($slowQueries),
            'active_profiles' => count($this->profiles),
        ];
    }

    /**
     * Get all logged queries.
     *
     * @return array<array{query: string, duration_ms: float, timestamp: float}>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Get slow queries only.
     *
     * @return array<array{query: string, duration_ms: float, timestamp: float}>
     */
    public function getSlowQueries(): array
    {
        return array_filter(
            $this->queries,
            fn ($q) => $q['duration_ms'] > self::SLOW_QUERY_THRESHOLD_MS
        );
    }

    /**
     * Reset all profiling data.
     */
    public function reset(): void
    {
        $this->profiles = [];
        $this->queries = [];

        $this->logger->debug('Performance profiler reset');
    }

    /**
     * Sanitize query for logging (remove sensitive data).
     */
    private function sanitizeQuery(string $query): string
    {
        // Remove values from queries for privacy
        $sanitized = preg_replace('/\b\d{13,}\b/', '[NUMBER]', $query); // Timestamps
        $sanitized = preg_replace('/\'[^\']{32,}\'/', '[STRING]', $sanitized ?? $query); // Long strings
        $sanitized = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', '[EMAIL]', $sanitized ?? $query); // Emails

        return $sanitized ?? $query;
    }

    /**
     * Profile a callable and return its result with metrics.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return array{result: T, metrics: array}
     */
    public function profile(string $section, callable $callback): array
    {
        $this->start($section);

        try {
            $result = $callback();
            $metrics = $this->stop($section);

            return [
                'result' => $result,
                'metrics' => $metrics,
            ];
        } catch (\Throwable $e) {
            $this->stop($section);

            throw $e;
        }
    }
}
