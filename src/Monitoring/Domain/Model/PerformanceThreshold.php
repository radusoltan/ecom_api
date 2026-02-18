<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Model;

use App\Monitoring\Domain\ValueObject\AlertSeverity;

/**
 * Performance Threshold Configuration.
 *
 * Defines performance thresholds for monitoring and alerting
 *
 * Business Rules (PRD Section 9.1):
 * - API response time < 200ms (p95)
 * - Database query time < 100ms
 * - Page load time < 2s (p95)
 * - Search query < 100ms
 * - Uptime SLA: 99.9%
 */
final readonly class PerformanceThreshold
{
    public function __construct(
        public string $metricName,
        public float $warningThreshold,
        public float $criticalThreshold,
        public string $unit = 'ms',
        public ?string $description = null,
    ) {
    }

    public static function apiResponseTime(): self
    {
        return new self(
            metricName: 'api_response_time',
            warningThreshold: 150.0,
            criticalThreshold: 200.0,
            unit: 'ms',
            description: 'API response time (p95)'
        );
    }

    public static function databaseQueryTime(): self
    {
        return new self(
            metricName: 'database_query_time',
            warningThreshold: 75.0,
            criticalThreshold: 100.0,
            unit: 'ms',
            description: 'Database query execution time'
        );
    }

    public static function pageLoadTime(): self
    {
        return new self(
            metricName: 'page_load_time',
            warningThreshold: 1500.0,
            criticalThreshold: 2000.0,
            unit: 'ms',
            description: 'Full page load time (p95)'
        );
    }

    public static function searchQueryTime(): self
    {
        return new self(
            metricName: 'search_query_time',
            warningThreshold: 75.0,
            criticalThreshold: 100.0,
            unit: 'ms',
            description: 'Search query response time'
        );
    }

    public static function cacheHitRate(): self
    {
        return new self(
            metricName: 'cache_hit_rate',
            warningThreshold: 80.0,
            criticalThreshold: 70.0,
            unit: '%',
            description: 'Cache hit rate (lower is worse)'
        );
    }

    public static function memoryUsage(): self
    {
        return new self(
            metricName: 'memory_usage',
            warningThreshold: 75.0,
            criticalThreshold: 90.0,
            unit: '%',
            description: 'Memory usage percentage'
        );
    }

    public static function errorRate(): self
    {
        return new self(
            metricName: 'error_rate',
            warningThreshold: 1.0,
            criticalThreshold: 5.0,
            unit: '%',
            description: 'Error rate percentage'
        );
    }

    public function evaluate(float $value): ?AlertSeverity
    {
        // For cache hit rate, lower is worse (inverted threshold)
        if ('cache_hit_rate' === $this->metricName) {
            if ($value <= $this->criticalThreshold) {
                return AlertSeverity::critical();
            }
            if ($value <= $this->warningThreshold) {
                return AlertSeverity::warning();
            }

            return null;
        }

        // For all other metrics, higher is worse
        if ($value >= $this->criticalThreshold) {
            return AlertSeverity::critical();
        }

        if ($value >= $this->warningThreshold) {
            return AlertSeverity::warning();
        }

        return null;
    }

    public function isViolated(float $value): bool
    {
        return null !== $this->evaluate($value);
    }
}
