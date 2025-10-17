<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Security;

interface TenantContextInterface
{
    public function getTenantId(): ?string;
}
