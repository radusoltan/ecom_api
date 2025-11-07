<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\ProductType;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class ProductTypeChanged
{
    public function __construct(
        public ProductId $productId,
        public TenantId $tenantId,
        public ProductType $oldType,
        public ProductType $newType,
        public \DateTimeImmutable $occurredAt
    ) {}
}
