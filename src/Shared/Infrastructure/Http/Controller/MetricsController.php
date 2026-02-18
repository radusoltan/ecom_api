<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Controller;

use App\Shared\Infrastructure\Metrics\MetricsCollector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Metrics Controller.
 *
 * Exposes Prometheus metrics endpoint at /metrics
 *
 * Security Note: In production, this endpoint should be:
 * 1. Protected by firewall (only accessible from monitoring servers)
 * 2. Or secured with authentication
 * 3. Or exposed on a separate port for internal monitoring
 *
 * @see EPIC 3.3 - Observability & Tracing
 */
final class MetricsController extends AbstractController
{
    public function __construct(
        private readonly MetricsCollector $metricsCollector,
    ) {
    }

    #[Route('/metrics', name: 'metrics', methods: ['GET'])]
    public function metrics(): Response
    {
        $prometheusOutput = $this->metricsCollector->exportPrometheusFormat();

        return new Response(
            $prometheusOutput,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/plain; version=0.0.4; charset=UTF-8',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]
        );
    }

    #[Route('/metrics/stats', name: 'metrics_stats', methods: ['GET'])]
    public function stats(): Response
    {
        $stats = $this->metricsCollector->getMetricStats();

        return $this->json($stats);
    }
}
