<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Single item for stock availability check (request).
 */
final class StockAvailabilityItemResource
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public ?string $productId = null,

        #[Assert\Uuid]
        public ?string $variantId = null,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public ?int $quantity = null,
    ) {
    }
}
