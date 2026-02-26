<?php

declare(strict_types=1);

namespace App\Monitoring\Application\Query;

use App\Monitoring\Infrastructure\Service\ApplicationPerformanceMonitor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPerformanceStatusQueryHandler
{
    public function __construct(
        private ApplicationPerformanceMonitor $apm,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetPerformanceStatusQuery $query): array
    {
        return $this->apm->getPerformanceStatus();
    }
}
