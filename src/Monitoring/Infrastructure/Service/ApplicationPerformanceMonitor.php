<?php

declare(strict_types=1);

namespace App\Monitoring\Infrastructure\Service;

use App\Monitoring\Domain\Model\PerformanceThreshold;
use App\Monitoring\Domain\ValueObject\AlertSeverity;
use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Infrastructure\Metrics\MetricsCollector;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Application Performance Monitor (APM).
 *
 * Comprehensive performance monitoring service that:
 * - Tracks application performance metrics
 * - Detects performance degradation
 * - Triggers alerts when thresholds are breached
 * - Provides real-time performance insights
 * - Integrates with existing MetricsCollector and PerformanceProfiler
 *
 * Performance Targets (PRD Section 9.1):
 * - API response < 200ms (p95)
 * - Database query < 100ms
 * - Page load < 2s (p95)
 * - Search < 100ms
 * - Cache hit rate > 80%
 * - Uptime SLA: 99.9%
 */
final class ApplicationPerformanceMonitor
{
    /** @var PerformanceThreshold[] */
    private array $thresholds;

    /** @var array<string, array{timestamp: float, value: float, severity: string}> */
    private array $activeAlerts = [];

    /** @var array<string, mixed> */
    private array $performanceHistory = [];

    public function __construct(
        private readonly MetricsCollector $metricsCollector,
        private readonly PerformanceProfiler $performanceProfiler,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
        $this->initializeThresholds();
    }

    private function initializeThresholds(): void
    {
        $this->thresholds = [
            'api_response_time' => PerformanceThreshold::apiResponseTime(),
            'database_query_time' => PerformanceThreshold::databaseQueryTime(),
            'page_load_time' => PerformanceThreshold::pageLoadTime(),
            'search_query_time' => PerformanceThreshold::searchQueryTime(),
            'cache_hit_rate' => PerformanceThreshold::cacheHitRate(),
            'memory_usage' => PerformanceThreshold::memoryUsage(),
            'error_rate' => PerformanceThreshold::errorRate(),
        ];
    }

    /**
     * Track API request performance.
     */
    public function trackApiRequest(
        string $route,
        string $method,
        float $durationMs,
        int $statusCode,
        ?string $tenantId = null,
    ): void {
        // Record metrics
        $labels = [
            'route' => $route,
            'method' => $method,
            'status' => (string) $statusCode,
        ];

        if ($tenantId) {
            $labels['tenant_id'] = $tenantId;
        }

        $this->metricsCollector->incrementCounter('api_requests_total', $labels);
        $this->metricsCollector->observeHistogram('api_request_duration_seconds', $durationMs / 1000, $labels);

        // Check threshold
        $threshold = $this->thresholds['api_response_time'];
        $severity = $threshold->evaluate($durationMs);

        if (null !== $severity) {
            $this->triggerAlert(
                'api_response_time',
                $durationMs,
                $severity,
                sprintf('API endpoint %s %s exceeded threshold: %.2fms', $method, $route, $durationMs),
                $labels
            );
        }

        // Track in history
        $this->recordPerformanceMetric('api_response_time', $durationMs, $labels);
    }

    /**
     * Track database query performance.
     */
    public function trackDatabaseQuery(string $query, float $durationMs): void
    {
        $threshold = $this->thresholds['database_query_time'];
        $severity = $threshold->evaluate($durationMs);

        if (null !== $severity) {
            $this->triggerAlert(
                'database_query_time',
                $durationMs,
                $severity,
                sprintf('Slow database query detected: %.2fms', $durationMs),
                ['query' => substr($query, 0, 100)]
            );
        }

        $this->recordPerformanceMetric('database_query_time', $durationMs);
    }

    /**
     * Track cache performance.
     */
    public function trackCacheOperation(string $operation, bool $hit, float $durationMs = 0): void
    {
        $labels = [
            'operation' => $operation,
            'result' => $hit ? 'hit' : 'miss',
        ];

        $this->metricsCollector->incrementCounter('cache_operations_total', $labels);

        if ($durationMs > 0) {
            $this->metricsCollector->observeHistogram('cache_operation_duration_seconds', $durationMs / 1000, $labels);
        }
    }

    /**
     * Get cache hit rate.
     */
    public function getCacheHitRate(int $periodSeconds = 3600): float
    {
        // This would typically query from a time-series database
        // For now, we'll calculate from recent metrics
        $cacheKey = 'apm_cache_hit_rate_'.$periodSeconds;

        return $this->cache->get($cacheKey, function () {
            // Calculate from metrics (simplified)
            // In production, this would aggregate from Prometheus/TimescaleDB
            return 85.0; // Default value
        });
    }

    /**
     * Check all thresholds and return violations.
     *
     * @return array<string, array{metric: string, value: float, threshold: float, severity: string}>
     */
    public function checkAllThresholds(): array
    {
        $violations = [];

        // Check API response time (from profiler summary)
        $summary = $this->performanceProfiler->getSummary();

        // Check memory usage
        $memoryUsagePercent = ($summary['memory_current_mb'] / (int) ini_get('memory_limit')) * 100;
        $memorySeverity = $this->thresholds['memory_usage']->evaluate($memoryUsagePercent);

        if (null !== $memorySeverity) {
            $violations['memory_usage'] = [
                'metric' => 'memory_usage',
                'value' => $memoryUsagePercent,
                'threshold' => $this->thresholds['memory_usage']->warningThreshold,
                'severity' => $memorySeverity->value(),
            ];
        }

        // Check slow queries
        $slowQueryRate = $summary['queries_total'] > 0
            ? ($summary['queries_slow'] / $summary['queries_total']) * 100
            : 0;

        if ($slowQueryRate > 10) { // More than 10% slow queries
            $violations['slow_query_rate'] = [
                'metric' => 'slow_query_rate',
                'value' => $slowQueryRate,
                'threshold' => 10.0,
                'severity' => $slowQueryRate > 25 ? AlertSeverity::critical()->value() : AlertSeverity::warning()->value(),
            ];
        }

        return $violations;
    }

    /**
     * Get current performance status.
     */
    public function getPerformanceStatus(): array
    {
        $summary = $this->performanceProfiler->getSummary();
        $violations = $this->checkAllThresholds();

        return [
            'status' => empty($violations) ? 'healthy' : 'degraded',
            'timestamp' => time(),
            'summary' => $summary,
            'violations' => $violations,
            'active_alerts' => $this->activeAlerts,
            'thresholds' => array_map(fn ($t) => [
                'metric' => $t->metricName,
                'warning' => $t->warningThreshold,
                'critical' => $t->criticalThreshold,
                'unit' => $t->unit,
                'description' => $t->description,
            ], $this->thresholds),
        ];
    }

    /**
     * Get performance metrics for a specific time period.
     */
    public function getPerformanceMetrics(string $metric, int $periodSeconds = 3600): array
    {
        $cutoffTime = microtime(true) - $periodSeconds;

        if (!isset($this->performanceHistory[$metric])) {
            return [];
        }

        return array_filter(
            $this->performanceHistory[$metric],
            fn ($entry) => $entry['timestamp'] >= $cutoffTime
        );
    }

    /**
     * Get aggregated statistics for a metric.
     */
    public function getMetricStatistics(string $metric, int $periodSeconds = 3600): ?array
    {
        $metrics = $this->getPerformanceMetrics($metric, $periodSeconds);

        if (empty($metrics)) {
            return null;
        }

        $values = array_column($metrics, 'value');
        sort($values);
        $count = count($values);

        return [
            'metric' => $metric,
            'period_seconds' => $periodSeconds,
            'count' => $count,
            'min' => min($values),
            'max' => max($values),
            'avg' => array_sum($values) / $count,
            'p50' => $values[(int) ceil($count * 0.5) - 1] ?? 0,
            'p95' => $values[(int) ceil($count * 0.95) - 1] ?? 0,
            'p99' => $values[(int) ceil($count * 0.99) - 1] ?? 0,
        ];
    }

    /**
     * Trigger performance alert.
     */
    private function triggerAlert(
        string $metric,
        float $value,
        AlertSeverity $severity,
        string $message,
        array $context = [],
    ): void {
        $alertKey = $metric.'_'.$severity->value();

        // Prevent alert spam (only trigger if not recently triggered)
        if (isset($this->activeAlerts[$alertKey])) {
            $lastAlert = $this->activeAlerts[$alertKey];
            if (microtime(true) - $lastAlert['timestamp'] < 300) { // 5 minutes cooldown
                return;
            }
        }

        $this->activeAlerts[$alertKey] = [
            'timestamp' => microtime(true),
            'value' => $value,
            'severity' => $severity->value(),
        ];

        $logContext = array_merge($context, [
            'metric' => $metric,
            'value' => $value,
            'severity' => $severity->value(),
        ]);

        // Log based on severity
        match ($severity->value()) {
            'critical' => $this->logger->critical($message, $logContext),
            'error' => $this->logger->error($message, $logContext),
            'warning' => $this->logger->warning($message, $logContext),
            default => $this->logger->info($message, $logContext),
        };

        // Record alert metric
        $this->metricsCollector->incrementCounter('performance_alerts_total', [
            'metric' => $metric,
            'severity' => $severity->value(),
        ]);
    }

    /**
     * Record performance metric in history.
     */
    private function recordPerformanceMetric(string $metric, float $value, array $labels = []): void
    {
        if (!isset($this->performanceHistory[$metric])) {
            $this->performanceHistory[$metric] = [];
        }

        $this->performanceHistory[$metric][] = [
            'timestamp' => microtime(true),
            'value' => $value,
            'labels' => $labels,
        ];

        // Keep only last 1000 entries per metric to prevent memory bloat
        if (count($this->performanceHistory[$metric]) > 1000) {
            array_shift($this->performanceHistory[$metric]);
        }
    }

    /**
     * Clear all alerts.
     */
    public function clearAlerts(): void
    {
        $this->activeAlerts = [];
        $this->logger->info('Performance alerts cleared');
    }

    /**
     * Get health check status.
     */
    public function getHealthCheck(): array
    {
        $status = $this->getPerformanceStatus();
        $isHealthy = 'healthy' === $status['status'];

        return [
            'status' => $isHealthy ? 'pass' : 'warn',
            'checks' => [
                'performance' => [
                    'status' => $isHealthy ? 'pass' : 'warn',
                    'observedValue' => $status['summary'],
                    'observedUnit' => 'mixed',
                    'time' => date('c'),
                    'output' => $isHealthy ? 'All performance metrics within thresholds' : 'Some thresholds exceeded',
                ],
            ],
        ];
    }
}
