<?php

declare(strict_types=1);

namespace App\Monitoring\Presentation\Api\Controller;

use App\Monitoring\Infrastructure\Service\ApplicationPerformanceMonitor;
use App\Shared\Application\Service\PerformanceProfiler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Performance Monitoring API Controller.
 *
 * Provides REST API endpoints for performance monitoring:
 * - GET /api/monitoring/performance - Overall performance status
 * - GET /api/monitoring/performance/metrics/{metric} - Specific metric details
 * - GET /api/monitoring/performance/slow-queries - Slow query list
 * - GET /api/monitoring/performance/alerts - Active performance alerts
 * - GET /api/monitoring/health - Health check endpoint
 * - POST /api/monitoring/performance/alerts/clear - Clear all alerts
 *
 * @see PRD Section 9.1 - Performance Requirements
 */
#[Route('/api/monitoring', name: 'monitoring_')]
final class PerformanceMonitoringController extends AbstractController
{
    public function __construct(
        private readonly ApplicationPerformanceMonitor $apm,
        private readonly PerformanceProfiler $profiler,
    ) {
    }

    /**
     * Get overall performance status.
     *
     * @Route("/performance", name="performance_status", methods={"GET"})
     */
    #[Route('/performance', name: 'performance_status', methods: ['GET'])]
    public function getPerformanceStatus(): JsonResponse
    {
        $status = $this->apm->getPerformanceStatus();

        return $this->json($status);
    }

    /**
     * Get specific metric statistics.
     *
     * @Route("/performance/metrics/{metric}", name="metric_stats", methods={"GET"})
     */
    #[Route('/performance/metrics/{metric}', name: 'metric_stats', methods: ['GET'])]
    public function getMetricStatistics(string $metric, Request $request): JsonResponse
    {
        $period = $request->query->getInt('period', 3600); // Default: 1 hour

        $statistics = $this->apm->getMetricStatistics($metric, $period);

        if (null === $statistics) {
            return $this->json([
                'error' => 'No data available for metric',
                'metric' => $metric,
                'period' => $period,
            ], 404);
        }

        return $this->json($statistics);
    }

    /**
     * Get slow queries.
     *
     * @Route("/performance/slow-queries", name="slow_queries", methods={"GET"})
     */
    #[Route('/performance/slow-queries', name: 'slow_queries', methods: ['GET'])]
    public function getSlowQueries(): JsonResponse
    {
        $slowQueries = $this->profiler->getSlowQueries();

        return $this->json([
            'count' => count($slowQueries),
            'queries' => $slowQueries,
        ]);
    }

    /**
     * Get all queries (for debugging).
     *
     * @Route("/performance/queries", name="all_queries", methods={"GET"})
     */
    #[Route('/performance/queries', name: 'all_queries', methods: ['GET'])]
    public function getAllQueries(): JsonResponse
    {
        $queries = $this->profiler->getQueries();
        $summary = $this->profiler->getSummary();

        return $this->json([
            'summary' => $summary,
            'queries' => $queries,
        ]);
    }

    /**
     * Get active performance alerts.
     *
     * @Route("/performance/alerts", name="performance_alerts", methods={"GET"})
     */
    #[Route('/performance/alerts', name: 'performance_alerts', methods: ['GET'])]
    public function getActiveAlerts(): JsonResponse
    {
        $status = $this->apm->getPerformanceStatus();

        return $this->json([
            'active_alerts' => $status['active_alerts'],
            'violations' => $status['violations'],
            'timestamp' => $status['timestamp'],
        ]);
    }

    /**
     * Clear all performance alerts.
     *
     * @Route("/performance/alerts/clear", name="clear_alerts", methods={"POST"})
     */
    #[Route('/performance/alerts/clear', name: 'clear_alerts', methods: ['POST'])]
    public function clearAlerts(): JsonResponse
    {
        $this->apm->clearAlerts();

        return $this->json([
            'message' => 'All performance alerts cleared',
            'timestamp' => time(),
        ]);
    }

    /**
     * Health check endpoint (RFC 8631 compliant).
     *
     * @Route("/health", name="health_check", methods={"GET"})
     */
    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        $health = $this->apm->getHealthCheck();

        $statusCode = 'pass' === $health['status'] ? 200 : 503;

        return $this->json($health, $statusCode);
    }

    /**
     * Get performance summary for dashboard.
     *
     * @Route("/performance/dashboard", name="performance_dashboard", methods={"GET"})
     */
    #[Route('/performance/dashboard', name: 'performance_dashboard', methods: ['GET'])]
    public function getPerformanceDashboard(): JsonResponse
    {
        $status = $this->apm->getPerformanceStatus();
        $profilerSummary = $this->profiler->getSummary();
        $slowQueries = $this->profiler->getSlowQueries();

        // Calculate cache hit rate
        $cacheHitRate = $this->apm->getCacheHitRate(3600);

        // Get statistics for key metrics
        $apiStats = $this->apm->getMetricStatistics('api_response_time', 3600);
        $dbStats = $this->apm->getMetricStatistics('database_query_time', 3600);

        return $this->json([
            'overall_status' => $status['status'],
            'timestamp' => time(),
            'metrics' => [
                'api_response_time' => $apiStats,
                'database_query_time' => $dbStats,
                'cache_hit_rate' => [
                    'value' => $cacheHitRate,
                    'unit' => '%',
                    'status' => $cacheHitRate >= 80 ? 'healthy' : 'degraded',
                ],
                'memory' => [
                    'current_mb' => $profilerSummary['memory_current_mb'],
                    'peak_mb' => $profilerSummary['memory_peak_mb'],
                    'limit' => ini_get('memory_limit'),
                ],
                'queries' => [
                    'total' => $profilerSummary['queries_total'],
                    'slow' => $profilerSummary['queries_slow'],
                    'slow_percentage' => $profilerSummary['queries_total'] > 0
                        ? round(($profilerSummary['queries_slow'] / $profilerSummary['queries_total']) * 100, 2)
                        : 0,
                ],
            ],
            'alerts' => [
                'active' => count($status['active_alerts']),
                'violations' => count($status['violations']),
                'details' => $status['violations'],
            ],
            'slow_queries' => [
                'count' => count($slowQueries),
                'recent' => array_slice($slowQueries, -10), // Last 10
            ],
        ]);
    }
}
