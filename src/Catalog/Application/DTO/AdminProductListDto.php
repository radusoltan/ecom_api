<?php

declare(strict_types=1);

namespace App\Catalog\Application\DTO;

final readonly class AdminProductListDto
{
    /**
     * @param array{url: string, position?: int, isPrimary?: bool}|null $primaryImage
     */
    public function __construct(
        public string $id,
        public string $sku,
        public string $name,
        public string $slug,
        public int $priceAmount,
        public string $priceCurrency,
        public ?string $categoryId,
        public int $stockQuantity,
        public bool $trackInventory,
        public bool $active,
        public bool $isFeatured,
        public ?array $primaryImage,
        public ?string $description,
        public string $createdAt,
        public string $updatedAt,
        public int $variantCount = 0,
    ) {
    }
}
