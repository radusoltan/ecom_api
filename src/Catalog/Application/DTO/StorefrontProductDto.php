<?php

declare(strict_types=1);

namespace App\Catalog\Application\DTO;

use Symfony\Component\Serializer\Attribute\Groups;

final class StorefrontProductDto
{
    public function __construct(
        #[Groups(['storefront:read'])]
        public readonly string $id,
        #[Groups(['storefront:read'])]
        public readonly string $slug,
        #[Groups(['storefront:read'])]
        public readonly string $name,
        #[Groups(['storefront:read'])]
        public readonly MoneyDto $price,

        #[Groups(['storefront:read'])]
        public readonly ?ProductImageDto $primaryImage = null,

        #[Groups(['storefront:read'])]
        public readonly bool $isFeatured = false,
        #[Groups(['storefront:read'])]
        public readonly ?float $rating = null,
        #[Groups(['storefront:read'])]
        public readonly ?string $availability = null,
        #[Groups(['storefront:read'])]
        public readonly ?array $breadcrumbs = null, // [['name' => string, 'slug' => string], ...]

        #[Groups(['storefront:read'])]
        public readonly ?string $description = null,

        #[Groups(['storefront:read'])]
        public readonly ?string $categoryId = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $priceData = $data['price'] ?? [];
        $price = $priceData instanceof MoneyDto
            ? $priceData
            : new MoneyDto(
                amount: is_array($priceData) ? (int) ($priceData['amount'] ?? 0) : 0,
                currency: is_array($priceData) ? (string) ($priceData['currency'] ?? 'USD') : 'USD',
            );

        $imageData = $data['primaryImage'] ?? null;
        $primaryImage = match (true) {
            $imageData instanceof ProductImageDto => $imageData,
            is_array($imageData) => new ProductImageDto(
                urlSm: (string) ($imageData['urlSm'] ?? ''),
                urlMd: (string) ($imageData['urlMd'] ?? ''),
                urlLg: (string) ($imageData['urlLg'] ?? ''),
            ),
            default => null,
        };

        return new self(
            id: $data['id'] ?? '',
            slug: $data['slug'] ?? '',
            name: $data['name'] ?? '',
            price: $price,
            primaryImage: $primaryImage,
            isFeatured: $data['isFeatured'] ?? false,
            rating: $data['rating'] ?? null,
            availability: $data['availability'] ?? null,
            breadcrumbs: $data['breadcrumbs'] ?? null,
            description: $data['description'] ?? null,
            categoryId: $data['categoryId'] ?? null,
        );
    }
}
