<?php

declare(strict_types=1);

namespace App\Catalog\Application\DTO;

use Symfony\Component\Serializer\Annotation\Groups;

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
        public readonly array $price, // ['amount' => int, 'currency' => string]

        #[Groups(['storefront:read'])]
        public readonly ?array $primaryImage = null, // ['urlSm' => string, 'urlMd' => string, 'urlLg' => string]

        #[Groups(['storefront:read'])]
        public readonly bool $isFeatured = false,
        #[Groups(['storefront:read'])]
        public readonly ?float $rating = null,
        #[Groups(['storefront:read'])]
        public readonly ?string $availability = null,
        #[Groups(['storefront:read'])]
        public readonly ?array $breadcrumbs = null, // [['name' => string, 'slug' => string], ...]

        #[Groups(['storefront:read'])]
        public readonly ?string $description = null
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            slug: $data['slug'] ?? '',
            name: $data['name'] ?? '',
            price: $data['price'] ?? ['amount' => 0, 'currency' => 'USD'],
            primaryImage: $data['primaryImage'] ?? null,
            isFeatured: $data['isFeatured'] ?? false,
            rating: $data['rating'] ?? null,
            availability: $data['availability'] ?? null,
            breadcrumbs: $data['breadcrumbs'] ?? null,
            description: $data['description'] ?? null
        );
    }
}
