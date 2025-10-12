<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetCategories
{
    public function __construct(
        public TenantId $tenantId
    ) {}
}
