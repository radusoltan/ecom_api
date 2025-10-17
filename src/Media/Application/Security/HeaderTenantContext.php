<?php

declare(strict_types=1);

namespace App\Media\Application\Security;

use App\Media\Infrastructure\Security\TenantContextInterface;
use App\Shared\Infrastructure\Tenant\TenantContext;

final class HeaderTenantContext implements TenantContextInterface
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function getTenantId(): ?string
    {
        $tenantId = $this->tenantContext->getCurrentTenantId();

        return $tenantId?->toString();
    }
}
