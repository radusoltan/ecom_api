<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Metrics;

use App\Shared\Infrastructure\Metrics\MetricsCollector;
use PHPUnit\Framework\TestCase;

/**
 * Metrics Collector Unit Tests.
 *
 * Tests metric collection and Prometheus format export
 *
 * @group sprint3
 * @group unit
 */
final class MetricsCollectorTest extends TestCase
{
    private MetricsCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new MetricsCollector('test');
    }

    public function testIncrementCounter(): void
    {
        $this->collector->incrementCounter('test_counter', ['label1' => 'value1']);
        $this->collector->incrementCounter('test_counter', ['label1' => 'value1']);

        $output = $this->collector->exportPrometheusFormat();

        $this->assertStringContainsString('test_counter', $output);
        $this->assertStringContainsString('label1="value1"', $output);
        $this->assertStringContainsString('2', $output);
    }

    public function testObserveHistogram(): void
    {
        $this->collector->observeHistogram('test_histogram', 0.5, ['label1' => 'value1']);
        $this->collector->observeHistogram('test_histogram', 1.0, ['label1' => 'value1']);
        $this->collector->observeHistogram('test_histogram', 1.5, ['label1' => 'value1']);

        $output = $this->collector->exportPrometheusFormat();

        $this->assertStringContainsString('test_histogram', $output);
        $this->assertStringContainsString('_sum', $output);
        $this->assertStringContainsString('_count', $output);
        $this->assertStringContainsString('quantile', $output);
    }

    public function testSetGauge(): void
    {
        $this->collector->setGauge('test_gauge', 42.5, ['label1' => 'value1']);

        $output = $this->collector->exportPrometheusFormat();

        $this->assertStringContainsString('test_gauge', $output);
        $this->assertStringContainsString('label1="value1"', $output);
        $this->assertStringContainsString('42.5', $output);
    }

    public function testPrometheusFormatIncludesHelpAndType(): void
    {
        $this->collector->incrementCounter('order_placed_total', ['tenant_id' => 'test']);

        $output = $this->collector->exportPrometheusFormat();

        $this->assertStringContainsString('# HELP order_placed_total', $output);
        $this->assertStringContainsString('# TYPE order_placed_total counter', $output);
    }

    public function testMetricStatsCalculation(): void
    {
        // Add observations
        for ($i = 1; $i <= 100; ++$i) {
            $this->collector->observeHistogram('test_latency', $i / 100, ['endpoint' => 'test']);
        }

        $stats = $this->collector->getMetricStats();

        $this->assertArrayHasKey('test_latency', $stats);
        $this->assertIsArray($stats['test_latency']);
        $this->assertCount(1, $stats['test_latency']);

        $metric = $stats['test_latency'][0];
        $this->assertEquals(100, $metric['count']);
        $this->assertArrayHasKey('avg', $metric);
        $this->assertArrayHasKey('p50', $metric);
        $this->assertArrayHasKey('p95', $metric);
        $this->assertArrayHasKey('p99', $metric);
    }

    public function testMultipleLabelCombinations(): void
    {
        $this->collector->incrementCounter('test_counter', ['tenant' => 'A', 'status' => 'success']);
        $this->collector->incrementCounter('test_counter', ['tenant' => 'A', 'status' => 'failed']);
        $this->collector->incrementCounter('test_counter', ['tenant' => 'B', 'status' => 'success']);

        $output = $this->collector->exportPrometheusFormat();

        // Should have separate metrics for each label combination
        $this->assertStringContainsString('tenant="A"', $output);
        $this->assertStringContainsString('tenant="B"', $output);
        $this->assertStringContainsString('status="success"', $output);
        $this->assertStringContainsString('status="failed"', $output);
    }

    public function testLabelsAreEscaped(): void
    {
        $this->collector->incrementCounter('test_counter', ['label' => 'value"with"quotes']);

        $output = $this->collector->exportPrometheusFormat();

        $this->assertStringContainsString('label="value\\"with\\"quotes"', $output);
    }

    public function testHistogramQuantileCalculation(): void
    {
        // Add 100 observations from 0.001 to 1.0
        for ($i = 1; $i <= 1000; ++$i) {
            $this->collector->observeHistogram('api_latency', $i / 1000, ['path' => '/api/test']);
        }

        $stats = $this->collector->getMetricStats();
        $metric = $stats['api_latency'][0];

        // p50 should be around 0.5
        $this->assertGreaterThan(0.49, $metric['p50']);
        $this->assertLessThan(0.51, $metric['p50']);

        // p95 should be around 0.95
        $this->assertGreaterThan(0.94, $metric['p95']);
        $this->assertLessThan(0.96, $metric['p95']);

        // p99 should be around 0.99
        $this->assertGreaterThan(0.98, $metric['p99']);
        $this->assertLessThan(1.0, $metric['p99']);
    }
}
