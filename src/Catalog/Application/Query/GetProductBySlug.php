<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Model\Slug;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetProductBySlug
{
    public function __construct(
        public TenantId $tenantId,
        public Slug $slug
    ) {
    }
}
