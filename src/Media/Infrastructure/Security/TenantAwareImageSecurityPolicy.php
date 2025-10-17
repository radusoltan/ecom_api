<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Security;

use App\Media\Domain\Service\ImageSecurityPolicy;
use App\Media\Domain\ValueObject\OwnerReference;
use App\Shared\Domain\ValueObject\TenantId;

final class TenantAwareImageSecurityPolicy implements ImageSecurityPolicy
{
    public function __construct(private readonly TenantContextInterface $tenantContext)
    {
    }

    public function canView(TenantId $tenantId, OwnerReference $owner): bool
    {
        $currentTenant = $this->tenantContext->getTenantId();

        if ($currentTenant === null) {
            return false;
        }

        return $currentTenant === $tenantId->toString();
    }

    public function canManage(TenantId $tenantId, OwnerReference $owner): bool
    {
        $currentTenant = $this->tenantContext->getTenantId();

        if ($currentTenant === null) {
            return false;
        }

        return $currentTenant === $tenantId->toString();
    }
}
