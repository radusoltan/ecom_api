<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\CategoryId;
use App\Shared\Domain\ValueObject\TenantId;
use App\Catalog\Domain\Model\CategoryName;

final readonly class CreateCategory
{
    public function __construct(
        public CategoryId $id,
        public TenantId $tenantId,
        public CategoryName $name,
        public ?string $description,
        public ?CategoryId $parentId,
        public int $position = 0
    ) {}
}
