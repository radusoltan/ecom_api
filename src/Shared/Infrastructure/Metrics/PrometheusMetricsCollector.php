<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Metrics;

use Artprima\PrometheusMetricsBundle\Metrics\MetricsGeneratorInterface;
use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;

/**
 * Prometheus metrics collector for catalog operations.
 *
 * Provides methods to record:
 * - Counters: Total events (image uploads, product creations, etc.)
 * - Histograms: Latency measurements (API latency, thumbnail generation)
 * - Gauges: Current state (cache hit ratio)
 *
 * All metrics are labeled with tenant_id for multi-tenant visibility.
 */
final class PrometheusMetricsCollector
{
    private const NAMESPACE = 'ecom';

    private ?Counter $imageUploadCounter = null;
    private ?Counter $productCreatedCounter = null;
    private ?Counter $categoryCreatedCounter = null;
    private ?Histogram $apiLatencyHistogram = null;
    private ?Histogram $thumbnailGenerationHistogram = null;
    private ?Gauge $cacheHitRatioGauge = null;

    public function __construct(
        private readonly CollectorRegistry $collectorRegistry
    ) {}

    /**
     * Increment image upload counter
     */
    public function incrementImageUpload(string $tenantId): void
    {
        $this->getImageUploadCounter()->inc(['tenant' => $tenantId]);
    }

    /**
     * Increment product created counter
     */
    public function incrementProductCreated(string $tenantId): void
    {
        $this->getProductCreatedCounter()->inc(['tenant' => $tenantId]);
    }

    /**
     * Increment category created counter
     */
    public function incrementCategoryCreated(string $tenantId): void
    {
        $this->getCategoryCreatedCounter()->inc(['tenant' => $tenantId]);
    }

    /**
     * Record API request latency
     *
     * @param string $tenantId Tenant identifier
     * @param string $route API route name
     * @param float $latencySeconds Latency in seconds
     */
    public function recordApiLatency(string $tenantId, string $route, float $latencySeconds): void
    {
        $this->getApiLatencyHistogram()->observe($latencySeconds, ['tenant' => $tenantId, 'route' => $route]);
    }

    /**
     * Record thumbnail generation time
     *
     * @param string $tenantId Tenant identifier
     * @param float $durationSeconds Duration in seconds
     */
    public function recordThumbnailGeneration(string $tenantId, float $durationSeconds): void
    {
        $this->getThumbnailGenerationHistogram()->observe($durationSeconds, ['tenant' => $tenantId]);
    }

    /**
     * Set cache hit ratio gauge
     *
     * @param string $tenantId Tenant identifier
     * @param float $ratio Cache hit ratio (0.0 to 1.0)
     */
    public function setCacheHitRatio(string $tenantId, float $ratio): void
    {
        $this->getCacheHitRatioGauge()->set($ratio, ['tenant' => $tenantId]);
    }

    private function getImageUploadCounter(): Counter
    {
        if (null === $this->imageUploadCounter) {
            $this->imageUploadCounter = $this->collectorRegistry->getOrRegisterCounter(
                self::NAMESPACE,
                'catalog_image_upload_total',
                'Total number of image uploads',
                ['tenant']
            );
        }

        return $this->imageUploadCounter;
    }

    private function getProductCreatedCounter(): Counter
    {
        if (null === $this->productCreatedCounter) {
            $this->productCreatedCounter = $this->collectorRegistry->getOrRegisterCounter(
                self::NAMESPACE,
                'catalog_product_created_total',
                'Total number of products created',
                ['tenant']
            );
        }

        return $this->productCreatedCounter;
    }

    private function getCategoryCreatedCounter(): Counter
    {
        if (null === $this->categoryCreatedCounter) {
            $this->categoryCreatedCounter = $this->collectorRegistry->getOrRegisterCounter(
                self::NAMESPACE,
                'catalog_category_created_total',
                'Total number of categories created',
                ['tenant']
            );
        }

        return $this->categoryCreatedCounter;
    }

    private function getApiLatencyHistogram(): Histogram
    {
        if (null === $this->apiLatencyHistogram) {
            $this->apiLatencyHistogram = $this->collectorRegistry->getOrRegisterHistogram(
                self::NAMESPACE,
                'catalog_api_latency_seconds',
                'Catalog API request latency in seconds',
                ['tenant', 'route'],
                [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10] // Buckets: 5ms to 10s
            );
        }

        return $this->apiLatencyHistogram;
    }

    private function getThumbnailGenerationHistogram(): Histogram
    {
        if (null === $this->thumbnailGenerationHistogram) {
            $this->thumbnailGenerationHistogram = $this->collectorRegistry->getOrRegisterHistogram(
                self::NAMESPACE,
                'thumbnail_generation_seconds',
                'Thumbnail generation time in seconds',
                ['tenant'],
                [0.1, 0.25, 0.5, 1, 2, 5, 10, 30] // Buckets: 100ms to 30s
            );
        }

        return $this->thumbnailGenerationHistogram;
    }

    private function getCacheHitRatioGauge(): Gauge
    {
        if (null === $this->cacheHitRatioGauge) {
            $this->cacheHitRatioGauge = $this->collectorRegistry->getOrRegisterGauge(
                self::NAMESPACE,
                'catalog_cache_hit_ratio',
                'Catalog cache hit ratio (0.0 to 1.0)',
                ['tenant']
            );
        }

        return $this->cacheHitRatioGauge;
    }
}
