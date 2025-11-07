<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetProducts
{
    public function __construct(
        public TenantId $tenantId,
        public int $limit = 100,
        public int $offset = 0
    ) {
    }
}
