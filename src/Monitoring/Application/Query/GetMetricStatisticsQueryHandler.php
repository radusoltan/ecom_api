<?php

declare(strict_types=1);

namespace App\Monitoring\Application\Query;

use App\Monitoring\Infrastructure\Service\ApplicationPerformanceMonitor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetMetricStatisticsQueryHandler
{
    public function __construct(
        private ApplicationPerformanceMonitor $apm,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(GetMetricStatisticsQuery $query): ?array
    {
        return $this->apm->getMetricStatistics($query->metric, $query->period);
    }
}
