<?php

declare(strict_types=1);

namespace App\Tenant\Application\Command;

use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EnableLocaleCommandHandler
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private PerformanceProfiler $profiler,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(EnableLocaleCommand $command): void
    {
        $this->profiler->start('tenant.enable_locale');

        try {
            $tenantId = TenantId::fromString($command->tenantId);
            $locale = LanguageCode::fromString($command->localeCode);

            // Load tenant from repository
            $tenant = $this->tenantRepository->findById($tenantId);
            if (!$tenant instanceof Tenant) {
                throw new TenantNotFoundException(sprintf('Tenant with ID "%s" not found', $command->tenantId));
            }

            // Enable locale
            $tenant->enableLocale($locale);

            // Persist changes
            $this->tenantRepository->save($tenant);

            $metrics = $this->profiler->stop('tenant.enable_locale');

            // Log if slow (> 200ms for write operations)
            if ($metrics['duration_ms'] > 200) {
                $this->logger->warning('Slow tenant locale enable detected', [
                    'tenant_id' => $command->tenantId,
                    'locale' => $command->localeCode,
                    'metrics' => $metrics,
                ]);
            }
        } catch (\Throwable $e) {
            $this->profiler->stop('tenant.enable_locale');

            throw $e;
        }
    }
}
