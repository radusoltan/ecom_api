<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Psr\Log\LoggerInterface;

/**
 * DBAL Middleware that sets the tenant context in PostgreSQL session
 * on every new connection.
 *
 * Replaces the former DBAL Events::postConnect listener which was
 * removed in DBAL 4.0.
 */
final class TenantConnectionSubscriber implements MiddlewareInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new class ($driver, $this->tenantContext, $this->logger) extends AbstractDriverMiddleware {
            public function __construct(
                DriverInterface $driver,
                private readonly TenantContext $tenantContext,
                private readonly LoggerInterface $logger,
            ) {
                parent::__construct($driver);
            }

            public function connect(array $params): ConnectionInterface
            {
                $connection = parent::connect($params);

                if (!$this->tenantContext->hasCurrentTenant()) {
                    return $connection;
                }

                $tenantId = $this->tenantContext->getCurrentTenantId();

                if (null === $tenantId) {
                    return $connection;
                }

                try {
                    $connection->exec(
                        "SELECT set_config('app.tenant_id', '".$tenantId->toString()."', false)"
                    );

                    $this->logger->info('Tenant context set in database', [
                        'tenant_id' => $tenantId->toString(),
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->warning('Could not set tenant context in database: '.$e->getMessage(), [
                        'tenant_id' => $tenantId->toString(),
                        'exception' => get_class($e),
                        'error' => $e->getMessage(),
                    ]);
                }

                return new class ($connection) extends AbstractConnectionMiddleware {
                };
            }
        };
    }
}
