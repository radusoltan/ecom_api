<?php

declare(strict_types=1);

namespace App\Search\Domain\Model;

use App\Catalog\Domain\Model\ProductId;

/**
 * ProductSearchHit Value Object
 *
 * Represents a single product search result with relevance score.
 */
final readonly class ProductSearchHit
{
    /**
     * @param array<string> $categoryIds
     */
    public function __construct(
        public ProductId $productId,
        public string $sku,
        public string $name,
        public ?string $description,
        public float $price,
        public string $currency,
        public ?string $imageUrl,
        public bool $isActive,
        public bool $isFeatured,
        public float $score,
        public array $categoryIds = [],
        public ?float $averageRating = null,
        public ?int $reviewCount = null,
    ) {}

    public function hasImage(): bool
    {
        return null !== $this->imageUrl && '' !== $this->imageUrl;
    }

    public function hasReviews(): bool
    {
        return null !== $this->reviewCount && $this->reviewCount > 0;
    }

    /**
     * Convert to array for API serialization
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId->toString(),
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'imageUrl' => $this->imageUrl,
            'isActive' => $this->isActive,
            'isFeatured' => $this->isFeatured,
            'score' => $this->score,
            'categoryIds' => $this->categoryIds,
            'averageRating' => $this->averageRating,
            'reviewCount' => $this->reviewCount,
        ];
    }
}
