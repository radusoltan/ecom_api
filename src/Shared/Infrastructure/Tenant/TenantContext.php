<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Tenant;

use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Connection;

/**
 * TenantContext manages the current tenant for multi-tenancy.
 *
 * This class stores the current tenant ID in memory and also sets the
 * PostgreSQL session variable `app.tenant_id` for Row-Level Security (RLS).
 */
final class TenantContext implements TenantContextInterface
{
    private ?TenantId $currentTenantId = null;

    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function setCurrentTenant(TenantId $tenantId): void
    {
        $this->currentTenantId = $tenantId;

        // Set PostgreSQL session variable for RLS
        // Using SET (not SET LOCAL) to persist across transactions within the same connection
        $this->connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $tenantId->toString())
        );
    }

    public function getCurrentTenantId(): ?TenantId
    {
        return $this->currentTenantId;
    }

    public function hasCurrentTenant(): bool
    {
        return null !== $this->currentTenantId;
    }

    public function clearCurrentTenant(): void
    {
        $this->currentTenantId = null;

        // Reset PostgreSQL session variable
        // Using RESET to clear the variable (faster than setting to empty string)
        try {
            $this->connection->executeStatement('RESET app.tenant_id');
        } catch (\Exception) {
            // Ignore errors during reset (connection might be closed)
        }
    }

    public function getTenantId(): ?string
    {
        return $this->currentTenantId?->toString();
    }
}
