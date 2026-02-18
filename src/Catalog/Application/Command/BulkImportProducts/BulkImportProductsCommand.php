<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\BulkImportProducts;

/**
 * Command to bulk import products from array data.
 */
final readonly class BulkImportProductsCommand
{
    /**
     * @param array<int, array<string, mixed>> $products Array of product data
     */
    public function __construct(
        public array $products,
    ) {
    }
}
