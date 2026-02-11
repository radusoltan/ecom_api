<?php

declare(strict_types=1);

namespace App\Tenant\Application\Command;

use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Tenant\Domain\Exception\TenantAlreadyExistsException;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateTenantCommandHandler
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private PerformanceProfiler $profiler,
        private LoggerInterface $logger,
        private TenantContextInterface $tenantContext
    ) {
    }

    public function __invoke(CreateTenantCommand $command): void
    {
        $this->profiler->start('tenant.create');

        try {
            $ownerEmail = Email::fromString($command->ownerEmail);

            // Check if tenant with this email already exists
            $existingTenant = $this->tenantRepository->findByOwnerEmail($ownerEmail);
            if ($existingTenant instanceof Tenant) {
                throw new TenantAlreadyExistsException(sprintf('Tenant with owner email "%s" already exists', $command->ownerEmail));
            }

            // Create tenant using factory method
            $tenant = Tenant::create(
                TenantName::fromString($command->name),
                $ownerEmail
            );

            // Set tenant context for RLS before persisting
            $this->tenantContext->setCurrentTenant($tenant->id());

            // Persist tenant
            $this->tenantRepository->save($tenant);

            $metrics = $this->profiler->stop('tenant.create');

            // Log if slow (> 200ms for write operations)
            if ($metrics['duration_ms'] > 200) {
                $this->logger->warning('Slow tenant creation detected', [
                    'tenant_name' => $command->name,
                    'metrics' => $metrics,
                ]);
            }
        } catch (\Throwable $e) {
            $this->profiler->stop('tenant.create');

            throw $e;
        }
    }
}
