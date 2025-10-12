<?php

declare(strict_types=1);

namespace App\Tenant\Application\Query;

use App\Shared\Infrastructure\Performance\PerformanceProfiler;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Application\DTO\TenantDTO;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetTenantByIdQueryHandler
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private PerformanceProfiler $profiler,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(GetTenantByIdQuery $query): TenantDTO
    {
        $this->profiler->start('tenant.get_by_id');

        try {
            $tenantId = TenantId::fromString($query->tenantId);

            $tenant = $this->tenantRepository->findById($tenantId);
            if (!$tenant instanceof Tenant) {
                throw new TenantNotFoundException(sprintf('Tenant with ID "%s" not found', $query->tenantId));
            }

            $result = TenantDTO::fromAggregate($tenant);

            $metrics = $this->profiler->stop('tenant.get_by_id');

            // Log if slow (> 100ms for read operations)
            if ($metrics['duration_ms'] > 100) {
                $this->logger->warning('Slow tenant query detected', [
                    'tenant_id' => $query->tenantId,
                    'metrics' => $metrics,
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->profiler->stop('tenant.get_by_id');
            throw $e;
        }
    }
}
