<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Security;

final class NullTenantContext implements TenantContextInterface
{
    public function getTenantId(): ?string
    {
        return null;
    }
}
