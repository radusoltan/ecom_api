<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ValidateProductImport;

/**
 * Command to validate product import data.
 */
final readonly class ValidateProductImportCommand
{
    /**
     * @param array<int, array<string, mixed>> $products Array of product data to validate
     */
    public function __construct(
        public array $products
    ) {
    }
}
