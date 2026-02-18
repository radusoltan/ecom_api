<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\ProductId;

/**
 * RemoveBundleCommand.
 *
 * Command to remove bundle configuration from a product.
 */
final readonly class RemoveBundleCommand
{
    public function __construct(
        public ProductId $bundleProductId,
    ) {
    }
}
