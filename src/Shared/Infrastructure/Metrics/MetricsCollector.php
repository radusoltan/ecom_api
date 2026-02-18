<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Metrics;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Metrics Collector Service.
 *
 * Collects application metrics for Prometheus exposition.
 *
 * Metrics collected:
 * - order_placed_total: Total number of orders placed (counter)
 * - order_duration_seconds: Order processing duration (histogram)
 * - order_status_change_total: Total status changes (counter)
 * - payment_total: Total payments processed (counter)
 * - payment_failed_total: Total failed payments (counter)
 * - api_request_duration_seconds: API request latency (histogram)
 *
 * All metrics include labels: tenant_id, status (where applicable)
 *
 * @see EPIC 3.3 - Observability & Tracing
 */
final class MetricsCollector
{
    /** @var array<string, mixed> */
    private array $counters = [];
    /** @var array<string, mixed> */
    private array $histograms = [];
    private array $gauges = [];

    public function __construct(
        #[Autowire('%env(APP_ENV)%')]
        private readonly string $environment,
    ) {
        $this->initializeMetrics();
    }

    private function initializeMetrics(): void
    {
        // Initialize counters
        $this->counters = [
            'order_placed_total' => [],
            'order_status_change_total' => [],
            'payment_total' => [],
            'payment_failed_total' => [],
            'payment_refunded_total' => [],
            'api_requests_total' => [],
            'rate_limit_hits_total' => [],
        ];

        // Initialize histograms
        $this->histograms = [
            'order_duration_seconds' => [],
            'payment_duration_seconds' => [],
            'api_request_duration_seconds' => [],
        ];

        // Initialize gauges
        $this->gauges = [
            'orders_pending' => [],
            'orders_processing' => [],
        ];
    }

    /**
     * Increment a counter metric.
     */
    public function incrementCounter(string $name, array $labels = [], float $value = 1.0): void
    {
        $key = $this->buildMetricKey($name, $labels);

        if (!isset($this->counters[$name])) {
            $this->counters[$name] = [];
        }

        if (!isset($this->counters[$name][$key])) {
            $this->counters[$name][$key] = [
                'value' => 0,
                'labels' => $labels,
            ];
        }

        $this->counters[$name][$key]['value'] += $value;
    }

    /**
     * Observe a value in a histogram.
     */
    public function observeHistogram(string $name, float $value, array $labels = []): void
    {
        $key = $this->buildMetricKey($name, $labels);

        if (!isset($this->histograms[$name])) {
            $this->histograms[$name] = [];
        }

        if (!isset($this->histograms[$name][$key])) {
            $this->histograms[$name][$key] = [
                'observations' => [],
                'labels' => $labels,
            ];
        }

        $this->histograms[$name][$key]['observations'][] = $value;
    }

    /**
     * Set a gauge value.
     */
    public function setGauge(string $name, float $value, array $labels = []): void
    {
        $key = $this->buildMetricKey($name, $labels);

        if (!isset($this->gauges[$name])) {
            $this->gauges[$name] = [];
        }

        $this->gauges[$name][$key] = [
            'value' => $value,
            'labels' => $labels,
        ];
    }

    /**
     * Get all metrics in Prometheus text format.
     */
    public function exportPrometheusFormat(): string
    {
        $output = [];

        // Export counters
        foreach ($this->counters as $metricName => $entries) {
            $output[] = sprintf('# HELP %s %s', $metricName, $this->getMetricHelp($metricName));
            $output[] = sprintf('# TYPE %s counter', $metricName);

            foreach ($entries as $entry) {
                $labelsStr = $this->formatLabels($entry['labels']);
                $output[] = sprintf('%s%s %s', $metricName, $labelsStr, $entry['value']);
            }
        }

        // Export histograms
        foreach ($this->histograms as $metricName => $entries) {
            $output[] = sprintf('# HELP %s %s', $metricName, $this->getMetricHelp($metricName));
            $output[] = sprintf('# TYPE %s histogram', $metricName);

            foreach ($entries as $entry) {
                $observations = $entry['observations'];
                if (empty($observations)) {
                    continue;
                }

                sort($observations);
                $count = count($observations);
                $sum = array_sum($observations);

                $labelsStr = $this->formatLabels($entry['labels']);

                // Calculate quantiles
                $quantiles = [0.5, 0.95, 0.99];
                foreach ($quantiles as $quantile) {
                    $index = (int) ceil($count * $quantile) - 1;
                    $index = max(0, min($index, $count - 1));
                    $value = $observations[$index];

                    $quantileLabels = array_merge($entry['labels'], ['quantile' => (string) $quantile]);
                    $quantileLabelsStr = $this->formatLabels($quantileLabels);
                    $output[] = sprintf('%s%s %s', $metricName, $quantileLabelsStr, $value);
                }

                // Sum and count
                $output[] = sprintf('%s_sum%s %s', $metricName, $labelsStr, $sum);
                $output[] = sprintf('%s_count%s %s', $metricName, $labelsStr, $count);
            }
        }

        // Export gauges
        foreach ($this->gauges as $metricName => $entries) {
            $output[] = sprintf('# HELP %s %s', $metricName, $this->getMetricHelp($metricName));
            $output[] = sprintf('# TYPE %s gauge', $metricName);

            foreach ($entries as $entry) {
                $labelsStr = $this->formatLabels($entry['labels']);
                $output[] = sprintf('%s%s %s', $metricName, $labelsStr, $entry['value']);
            }
        }

        return implode("\n", $output)."\n";
    }

    /**
     * Get metric statistics (for debugging/monitoring).
     */
    public function getMetricStats(): array
    {
        $stats = [];

        foreach ($this->histograms as $metricName => $entries) {
            foreach ($entries as $entry) {
                $observations = $entry['observations'];
                if (empty($observations)) {
                    continue;
                }

                sort($observations);
                $count = count($observations);

                $stats[$metricName][] = [
                    'labels' => $entry['labels'],
                    'count' => $count,
                    'sum' => array_sum($observations),
                    'avg' => array_sum($observations) / $count,
                    'min' => min($observations),
                    'max' => max($observations),
                    'p50' => $observations[(int) ceil($count * 0.5) - 1] ?? 0,
                    'p95' => $observations[(int) ceil($count * 0.95) - 1] ?? 0,
                    'p99' => $observations[(int) ceil($count * 0.99) - 1] ?? 0,
                ];
            }
        }

        return $stats;
    }

    private function buildMetricKey(string $name, array $labels): string
    {
        ksort($labels);

        return $name.'_'.md5(json_encode($labels));
    }

    private function formatLabels(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }

        $pairs = [];
        foreach ($labels as $key => $value) {
            $pairs[] = sprintf('%s="%s"', $key, addslashes($value));
        }

        return '{'.implode(',', $pairs).'}';
    }

    private function getMetricHelp(string $metricName): string
    {
        return match ($metricName) {
            'order_placed_total' => 'Total number of orders placed',
            'order_duration_seconds' => 'Order processing duration in seconds',
            'order_status_change_total' => 'Total number of order status changes',
            'payment_total' => 'Total number of payments processed',
            'payment_failed_total' => 'Total number of failed payments',
            'payment_refunded_total' => 'Total number of refunded payments',
            'payment_duration_seconds' => 'Payment processing duration in seconds',
            'api_requests_total' => 'Total number of API requests',
            'api_request_duration_seconds' => 'API request duration in seconds',
            'rate_limit_hits_total' => 'Total number of rate limit hits',
            'orders_pending' => 'Number of orders in pending status',
            'orders_processing' => 'Number of orders in processing status',
            default => 'Metric description not available',
        };
    }
}
