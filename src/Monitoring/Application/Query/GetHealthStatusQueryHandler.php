<?php

declare(strict_types=1);

namespace App\Monitoring\Application\Query;

use App\Monitoring\Application\Service\HealthCheckService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetHealthStatusQueryHandler
{
    public function __construct(
        private HealthCheckService $healthCheckService,
    ) {
    }

    /**
     * @return array{status: string, checks: array<string, array{status: string, time: string, output: string}>}
     */
    public function __invoke(GetHealthStatusQuery $query): array
    {
        return $this->healthCheckService->check();
    }
}
