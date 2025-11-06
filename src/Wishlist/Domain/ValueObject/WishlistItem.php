<?php

declare(strict_types=1);

namespace App\Wishlist\Domain\ValueObject;

use App\Catalog\Domain\Model\ProductId;

/**
 * Value object for Wishlist Item
 * Represents a product in a wishlist with metadata
 */
final readonly class WishlistItem
{
    private function __construct(
        private ProductId $productId,
        private \DateTimeImmutable $addedAt
    ) {}

    public static function create(ProductId $productId): self
    {
        return new self($productId, new \DateTimeImmutable());
    }

    public static function reconstitute(
        ProductId $productId,
        \DateTimeImmutable $addedAt
    ): self {
        return new self($productId, $addedAt);
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function addedAt(): \DateTimeImmutable
    {
        return $this->addedAt;
    }

    public function equals(self $other): bool
    {
        return $this->productId->equals($other->productId);
    }

    public function toArray(): array
    {
        return [
            'productId' => $this->productId->toString(),
            'addedAt' => $this->addedAt->format('Y-m-d H:i:s'),
        ];
    }
}
