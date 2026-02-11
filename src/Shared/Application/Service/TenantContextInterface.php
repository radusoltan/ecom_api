<?php

declare(strict_types=1);

namespace App\Shared\Application\Service;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Interface for TenantContext to manage multi-tenant context.
 *
 * This interface allows Application layer to depend on an abstraction
 * rather than the Infrastructure implementation, respecting DDD layer boundaries.
 */
interface TenantContextInterface
{
    public function setCurrentTenant(TenantId $tenantId): void;

    public function getCurrentTenantId(): ?TenantId;

    public function hasCurrentTenant(): bool;

    public function clearCurrentTenant(): void;

    public function getTenantId(): ?string;
}
