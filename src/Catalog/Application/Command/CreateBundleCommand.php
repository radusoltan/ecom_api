<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\ProductId;

/**
 * CreateBundleCommand.
 *
 * Command to create a bundle configuration for a product.
 *
 * Business Rules:
 * - Product must be of type 'bundle'
 * - Items array contains: [{productId, quantity, price}, ...]
 * - Discount: 0-50%
 * - Cannot add bundle products to bundle (circular reference)
 */
final readonly class CreateBundleCommand
{
    /**
     * @param array<array{productId: string, quantity: int, price: array{amount: int, currency: string}}> $items
     */
    public function __construct(
        public ProductId $bundleProductId,
        public array $items,
        public float $discountPercentage = 0.0
    ) {
    }
}
