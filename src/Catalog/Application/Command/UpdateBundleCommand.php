<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\ProductId;

/**
 * UpdateBundleCommand.
 *
 * Command to update an existing bundle configuration.
 */
final readonly class UpdateBundleCommand
{
    /**
     * @param array<array{productId: string, quantity: int, price: array{amount: int, currency: string}}> $items
     */
    public function __construct(
        public ProductId $bundleProductId,
        public array $items,
        public float $discountPercentage = 0.0,
    ) {
    }
}
