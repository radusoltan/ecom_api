<?php

declare(strict_types=1);

namespace App\Cart\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;

/**
 * CartItem Entity (part of Cart Aggregate).
 *
 * Business Rules:
 * - CartItem belongs to a Cart (aggregate root)
 * - Quantity must be between 1 and 999
 * - Unit price is snapshot at time of adding to cart (doesn't auto-update)
 * - Row total is calculated as quantity × unit price
 * - Duplicate items (same product+variant) should be merged by increasing quantity
 */
final class CartItem
{
    private CartItemId $id;
    private ProductId $productId;
    private ?string $variantId;
    private Quantity $quantity;
    private Money $unitPrice;

    private function __construct()
    {
    }

    public static function create(
        CartItemId $id,
        ProductId $productId,
        ?string $variantId,
        Quantity $quantity,
        Money $unitPrice,
    ): self {
        $item = new self();
        $item->id = $id;
        $item->productId = $productId;
        $item->variantId = $variantId;
        $item->quantity = $quantity;
        $item->unitPrice = $unitPrice;

        return $item;
    }

    public function updateQuantity(Quantity $newQuantity): void
    {
        $this->quantity = $newQuantity;
    }

    public function increaseQuantity(Quantity $additionalQuantity): void
    {
        $this->quantity = $this->quantity->add($additionalQuantity);
    }

    public function rowTotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity->toInt());
    }

    /**
     * Check if this item represents the same product+variant combination.
     */
    public function isSameAs(ProductId $productId, ?string $variantId): bool
    {
        return $this->productId->equals($productId) && $this->variantId === $variantId;
    }

    public function id(): CartItemId
    {
        return $this->id;
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function variantId(): ?string
    {
        return $this->variantId;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }
}
