<?php

declare(strict_types=1);

namespace App\Monitoring\Application\Query;

final readonly class GetMetricStatisticsQuery
{
    public function __construct(
        public string $metric,
        public int $period = 3600,
    ) {
    }
}
