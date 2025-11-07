<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;
use Psr\Log\LoggerInterface;

#[AsDoctrineListener(event: Events::postConnect)]
final readonly class TenantConnectionSubscriber
{
    public function __construct(
        private TenantContext $tenantContext,
        private LoggerInterface $logger
    ) {
    }

    public function postConnect(ConnectionEventArgs $args): void
    {
        $connection = $args->getConnection();

        // If we have a current tenant, set it in PostgreSQL session
        if (!$this->tenantContext->hasCurrentTenant()) {
            return;
        }

        $tenantId = $this->tenantContext->getCurrentTenantId();

        if (null === $tenantId) {
            return;
        }

        try {
            // Execute PostgreSQL function to set current tenant
            // NOTE: This function will be created in Sprint 6 (Multi-tenancy Hardening)
            // For now, we use set_config() function because SET doesn't support prepared statements
            $connection->executeStatement(
                "SELECT set_config('app.tenant_id', ?, false)",
                [$tenantId->toString()]
            );

            $this->logger->info('Tenant context set in database', [
                'tenant_id' => $tenantId->toString(),
            ]);
        } catch (\Throwable $e) {
            // Log warning but don't fail - tenant isolation is handled at application level
            $this->logger->warning('Could not set tenant context in database: '.$e->getMessage(), [
                'tenant_id' => $tenantId->toString(),
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
