<?php

declare(strict_types=1);

namespace App\Monitoring\Presentation\Api\Controller;

use App\Monitoring\Application\Command\ClearAlertsCommand;
use App\Monitoring\Application\Query\GetHealthStatusQuery;
use App\Monitoring\Application\Query\GetMetricStatisticsQuery;
use App\Monitoring\Application\Query\GetPerformanceStatusQuery;
use App\Shared\Application\Service\PerformanceProfiler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
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
#[Route('/api/v1/monitoring', name: 'monitoring_')]
final class PerformanceMonitoringController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly MessageBusInterface $commandBus,
        private readonly PerformanceProfiler $profiler,
    ) {
    }

    #[Route('/performance', name: 'performance_status', methods: ['GET'])]
    public function getPerformanceStatus(): JsonResponse
    {
        $status = $this->handleQuery(new GetPerformanceStatusQuery());

        return $this->json($status);
    }

    #[Route('/performance/metrics/{metric}', name: 'metric_stats', methods: ['GET'])]
    public function getMetricStatistics(string $metric, Request $request): JsonResponse
    {
        $period = $request->query->getInt('period', 3600);

        $statistics = $this->handleQuery(new GetMetricStatisticsQuery($metric, $period));

        if (null === $statistics) {
            return $this->json([
                'error' => 'No data available for metric',
                'metric' => $metric,
                'period' => $period,
            ], 404);
        }

        return $this->json($statistics);
    }

    #[Route('/performance/slow-queries', name: 'slow_queries', methods: ['GET'])]
    public function getSlowQueries(): JsonResponse
    {
        $slowQueries = $this->profiler->getSlowQueries();

        return $this->json([
            'count' => count($slowQueries),
            'queries' => $slowQueries,
        ]);
    }

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

    #[Route('/performance/alerts', name: 'performance_alerts', methods: ['GET'])]
    public function getActiveAlerts(): JsonResponse
    {
        $status = $this->handleQuery(new GetPerformanceStatusQuery());

        return $this->json([
            'active_alerts' => $status['active_alerts'],
            'violations' => $status['violations'],
            'timestamp' => $status['timestamp'],
        ]);
    }

    #[Route('/performance/alerts/clear', name: 'clear_alerts', methods: ['POST'])]
    public function clearAlerts(): JsonResponse
    {
        $this->commandBus->dispatch(new ClearAlertsCommand());

        return $this->json([
            'message' => 'All performance alerts cleared',
            'timestamp' => time(),
        ]);
    }

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        $health = $this->handleQuery(new GetHealthStatusQuery());

        $statusCode = 'pass' === $health['status'] ? 200 : 503;

        return $this->json($health, $statusCode);
    }

    #[Route('/performance/dashboard', name: 'performance_dashboard', methods: ['GET'])]
    public function getPerformanceDashboard(): JsonResponse
    {
        $status = $this->handleQuery(new GetPerformanceStatusQuery());
        $profilerSummary = $this->profiler->getSummary();
        $slowQueries = $this->profiler->getSlowQueries();

        $apiStats = $this->handleQuery(new GetMetricStatisticsQuery('api_response_time', 3600));
        $dbStats = $this->handleQuery(new GetMetricStatisticsQuery('database_query_time', 3600));

        return $this->json([
            'overall_status' => $status['status'],
            'timestamp' => time(),
            'metrics' => [
                'api_response_time' => $apiStats,
                'database_query_time' => $dbStats,
                'cache_hit_rate' => [
                    'value' => $status['cache_hit_rate'] ?? 0,
                    'unit' => '%',
                    'status' => ($status['cache_hit_rate'] ?? 0) >= 80 ? 'healthy' : 'degraded',
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
                'recent' => array_slice($slowQueries, -10),
            ],
        ]);
    }

    private function handleQuery(object $query): mixed
    {
        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        return $handledStamp?->getResult();
    }
}
