<?php

declare(strict_types=1);

namespace App\Media\Domain\Service;

use App\Media\Domain\ValueObject\OwnerReference;
use App\Shared\Domain\ValueObject\TenantId;

interface ImageSecurityPolicy
{
    public function canView(TenantId $tenantId, OwnerReference $owner): bool;

    public function canManage(TenantId $tenantId, OwnerReference $owner): bool;
}
