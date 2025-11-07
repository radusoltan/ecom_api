<?php

declare(strict_types=1);

namespace App\Tenant\Application\Query;

use App\Shared\Application\Service\PerformanceProfiler;
use App\Tenant\Application\DTO\TenantDTO;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAllTenantsQueryHandler
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private PerformanceProfiler $profiler,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @return TenantDTO[]
     */
    public function __invoke(GetAllTenantsQuery $query): array
    {
        $this->profiler->start('tenant.get_all');

        try {
            $tenants = $this->tenantRepository->findAll();

            $result = array_map(
                fn (Tenant $tenant): TenantDTO => TenantDTO::fromAggregate($tenant),
                $tenants
            );

            $metrics = $this->profiler->stop('tenant.get_all');

            // Log if slow (> 100ms for read operations)
            if ($metrics['duration_ms'] > 100) {
                $this->logger->warning('Slow tenant list query detected', [
                    'tenant_count' => count($result),
                    'metrics' => $metrics,
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->profiler->stop('tenant.get_all');

            throw $e;
        }
    }
}
