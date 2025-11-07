<?php

declare(strict_types=1);

namespace App\Wishlist\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;
use App\Wishlist\Domain\Event\ItemAddedToWishlist;
use App\Wishlist\Domain\Event\ItemRemovedFromWishlist;
use App\Wishlist\Domain\Event\WishlistCleared;
use App\Wishlist\Domain\ValueObject\WishlistId;
use App\Wishlist\Domain\ValueObject\WishlistItem;

/**
 * Wishlist Aggregate Root
 * Represents a customer's wishlist of desired products.
 *
 * Business Rules:
 * - One wishlist per customer per tenant
 * - Maximum 100 products in wishlist
 * - A product can only be added once
 * - Items are sorted by date added (newest first)
 */
final class Wishlist extends AggregateRoot
{
    private const MAX_ITEMS = 100;

    /** @var array<string, WishlistItem> */
    private array $items = [];

    private function __construct(
        private WishlistId $id,
        private string $customerId,
        private TenantId $tenantId,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    /**
     * Create a new wishlist for a customer.
     */
    public static function create(
        WishlistId $id,
        string $customerId,
        TenantId $tenantId
    ): self {
        return new self(
            $id,
            $customerId,
            $tenantId,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstituteFromPersistence(
        WishlistId $id,
        string $customerId,
        TenantId $tenantId,
        array $items,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ): self {
        $wishlist = new self($id, $customerId, $tenantId, $createdAt, $updatedAt);
        $wishlist->items = $items;

        return $wishlist;
    }

    /**
     * Add a product to the wishlist.
     */
    public function addItem(ProductId $productId): void
    {
        $productIdString = $productId->toString();

        // Business rule: product already in wishlist
        if (isset($this->items[$productIdString])) {
            throw new \DomainException('Product is already in the wishlist');
        }

        // Business rule: maximum items limit
        if (count($this->items) >= self::MAX_ITEMS) {
            throw new \DomainException(sprintf('Wishlist cannot contain more than %d items', self::MAX_ITEMS));
        }

        $item = WishlistItem::create($productId);
        $this->items[$productIdString] = $item;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ItemAddedToWishlist(
            $this->id,
            $productId,
            $this->customerId,
            new \DateTimeImmutable()
        ));
    }

    /**
     * Remove a product from the wishlist.
     */
    public function removeItem(ProductId $productId): void
    {
        $productIdString = $productId->toString();

        if (!isset($this->items[$productIdString])) {
            throw new \DomainException('Product is not in the wishlist');
        }

        unset($this->items[$productIdString]);
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ItemRemovedFromWishlist(
            $this->id,
            $productId,
            $this->customerId,
            new \DateTimeImmutable()
        ));
    }

    /**
     * Clear all items from the wishlist.
     */
    public function clear(): void
    {
        $itemCount = count($this->items);

        if (0 === $itemCount) {
            return;
        }

        $this->items = [];
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new WishlistCleared(
            $this->id,
            $this->customerId,
            $itemCount,
            new \DateTimeImmutable()
        ));
    }

    /**
     * Check if a product is in the wishlist.
     */
    public function hasItem(ProductId $productId): bool
    {
        return isset($this->items[$productId->toString()]);
    }

    /**
     * Get the number of items in the wishlist.
     */
    public function itemCount(): int
    {
        return count($this->items);
    }

    /**
     * Check if the wishlist is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    // Getters

    public function id(): WishlistId
    {
        return $this->id;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    /**
     * @return WishlistItem[]
     */
    public function items(): array
    {
        return array_values($this->items);
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
