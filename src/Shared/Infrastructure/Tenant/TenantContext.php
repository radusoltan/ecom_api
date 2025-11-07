<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Tenant;

use App\Shared\Domain\ValueObject\TenantId;

final class TenantContext
{
    private ?TenantId $currentTenantId = null;

    public function setCurrentTenant(TenantId $tenantId): void
    {
        $this->currentTenantId = $tenantId;
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
    }

    public function getTenantId(): ?string
    {
        return $this->currentTenantId?->toString();
    }
}
