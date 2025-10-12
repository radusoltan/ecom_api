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
        public float $price,
        public string $currency,
        public string $status,
        public array $categoryIds,
        public ?string $imageUrl,
        public string $locale,
        public float $score,
    ) {}

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
            price: $source['price'],
            currency: $source['currency'],
            status: $source['status'],
            categoryIds: $source['category_ids'] ?? [],
            imageUrl: $source['image_url'] ?? null,
            locale: $source['locale'],
            score: $hit['_score'] ?? 0.0,
        );
    }
}
