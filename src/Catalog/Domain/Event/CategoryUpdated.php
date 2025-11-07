<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\CategoryId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class CategoryUpdated
{
    public function __construct(
        public CategoryId $categoryId,
        public TenantId $tenantId
    ) {
    }
}
