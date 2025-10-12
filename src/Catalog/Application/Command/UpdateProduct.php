<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class UpdateProduct
{
    public function __construct(
        public ProductId $id,
        public TenantId $tenantId,
        public ProductName $name,
        public ?string $description,
        public ?string $shortDescription,
        public Money $price,
        public ?CategoryId $categoryId
    ) {}
}
