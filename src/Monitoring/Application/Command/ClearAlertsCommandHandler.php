<?php

declare(strict_types=1);

namespace App\Monitoring\Application\Command;

use App\Monitoring\Infrastructure\Service\ApplicationPerformanceMonitor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ClearAlertsCommandHandler
{
    public function __construct(
        private ApplicationPerformanceMonitor $apm,
    ) {
    }

    public function __invoke(ClearAlertsCommand $command): void
    {
        $this->apm->clearAlerts();
    }
}
