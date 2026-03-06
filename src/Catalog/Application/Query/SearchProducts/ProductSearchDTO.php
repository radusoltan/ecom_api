<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\SearchProducts;

final readonly class ProductSearchDTO
{
    /**
     * @param array<string> $categoryIds
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $sku,
        public string $name,
        public ?string $description,
        public string $slug,
        public int $priceInCents,
        public string $currency,
        public string $status,
        public array $categoryIds,
        public ?string $imageUrl,
        public string $locale,
        public float $score,
        public float $averageRating = 0.0,
        public int $reviewCount = 0,
        public bool $isFeatured = false,
    ) {
    }

    /**
     * @param array<string, mixed> $hit
     */
    public static function fromElasticsearchHit(array $hit): self
    {
        $source = $hit['_source'];

        return new self(
            id: $source['id'],
            tenantId: $source['tenant_id'],
            sku: $source['sku'],
            name: $source['name'],
            description: $source['description'] ?? null,
            slug: $source['slug'],
            priceInCents: (int) round((float) ($source['price'] ?? 0) * 100),
            currency: $source['currency'],
            status: $source['status'],
            categoryIds: $source['category_ids'] ?? [],
            imageUrl: $source['image_url'] ?? null,
            locale: $source['locale'],
            score: $hit['_score'] ?? 0.0,
            averageRating: (float) ($source['average_rating'] ?? 0.0),
            reviewCount: (int) ($source['review_count'] ?? 0),
            isFeatured: (bool) ($source['is_featured'] ?? false),
        );
    }
}
