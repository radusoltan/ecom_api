<?php

declare(strict_types=1);

namespace App\Catalog\Application\DTO;

use Symfony\Component\Serializer\Annotation\Groups;

final class StorefrontCategoryDto
{
    /**
     * @param array<string, string>|null $image ['urlSm' => string, 'urlMd' => string, 'urlLg' => string]
     */
    public function __construct(
        #[Groups(['storefront:read'])]
        public readonly string $id,
        #[Groups(['storefront:read'])]
        public readonly string $slug,
        #[Groups(['storefront:read'])]
        public readonly string $name,
        #[Groups(['storefront:read'])]
        public readonly ?array $image = null,
        #[Groups(['storefront:read'])]
        public readonly bool $showOnFront = false,
        #[Groups(['storefront:read'])]
        public readonly int $childrenCount = 0,
        #[Groups(['storefront:read'])]
        public readonly ?string $description = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            slug: $data['slug'] ?? '',
            name: $data['name'] ?? '',
            image: $data['image'] ?? null,
            showOnFront: $data['showOnFront'] ?? false,
            childrenCount: $data['childrenCount'] ?? 0,
            description: $data['description'] ?? null
        );
    }
}
