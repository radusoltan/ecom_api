<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Repository;

interface MetricRepositoryInterface
{
    /**
     * Record a metric data point.
     *
     * @param array<string, string> $labels
     */
    public function record(string $metric, float $value, array $labels = []): void;

    /**
     * Get metric data points for a time period.
     *
     * @return list<array{timestamp: float, value: float, labels: array<string, string>}>
     */
    public function getMetrics(string $metric, int $periodSeconds = 3600): array;

    /**
     * Get aggregated statistics for a metric.
     *
     * @return array{metric: string, count: int, min: float, max: float, avg: float, p50: float, p95: float, p99: float}|null
     */
    public function getStatistics(string $metric, int $periodSeconds = 3600): ?array;
}
