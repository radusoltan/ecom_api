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

        if ($tenantId === null) {
            return;
        }

        try {
            // Execute PostgreSQL function to set current tenant
            $connection->executeStatement(
                "SELECT set_current_tenant_id(?)",
                [$tenantId->toString()]
            );

            $this->logger->info('Tenant context set in database', [
                'tenant_id' => $tenantId->toString(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to set tenant context in database', [
                'tenant_id' => $tenantId->toString(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
