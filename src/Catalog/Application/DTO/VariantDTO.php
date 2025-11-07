<?php

declare(strict_types=1);

namespace App\Catalog\Application\DTO;

/**
 * Data Transfer Object for Variant.
 */
final readonly class VariantDTO
{
    /**
     * @param array<string, string>                                     $optionValueMap - Map of option code to value code
     * @param array<array{url: string, position: int, isPrimary: bool}> $images
     */
    public function __construct(
        public string $id,
        public string $sku,
        public array $optionValueMap,
        public int $priceAmount,
        public string $priceCurrency,
        public int $stockQuantity,
        public bool $trackInventory,
        public bool $allowBackorder,
        public bool $isActive,
        public bool $isAvailable,
        public array $images = []
    ) {
    }
}
