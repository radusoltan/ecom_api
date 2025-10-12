<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Single item in a bulk stock operation request
 */
final class BulkStockOperationItem
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public ?string $productId = null,

        #[Assert\NotBlank]
        #[Assert\Ulid]
        public ?string $warehouseId = null,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public ?int $quantity = null,
    ) {
    }
}
