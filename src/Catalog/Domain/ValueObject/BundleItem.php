<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;

/**
 * BundleItem Value Object.
 *
 * Represents a single item within a product bundle.
 *
 * Business Rules (from PRD):
 * - Each bundle item references a product (cannot be another bundle - no circular references)
 * - Quantity must be at least 1
 * - Price is captured at bundle creation time (fixed price, not dynamic)
 * - Immutable once created
 */
final readonly class BundleItem
{
    private function __construct(
        private ProductId $productId,
        private int $quantity,
        private Money $price,
    ) {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Bundle item quantity must be at least 1');
        }
    }

    public static function create(
        ProductId $productId,
        int $quantity,
        Money $price,
    ): self {
        return new self($productId, $quantity, $price);
    }

    /**
     * Reconstitute from persistence (for repository).
     */
    public static function fromPersistence(
        ProductId $productId,
        int $quantity,
        Money $price,
    ): self {
        return new self($productId, $quantity, $price);
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function price(): Money
    {
        return $this->price;
    }

    /**
     * Calculate total price for this bundle item (price × quantity).
     */
    public function totalPrice(): Money
    {
        return $this->price->multiplyBy($this->quantity);
    }

    public function equals(self $other): bool
    {
        return $this->productId->equals($other->productId)
            && $this->quantity === $other->quantity
            && $this->price->equals($other->price);
    }

    /**
     * Convert to array representation (for persistence/serialization).
     *
     * @return array{productId: string, quantity: int, priceAmount: int, priceCurrency: string}
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId->toString(),
            'quantity' => $this->quantity,
            'priceAmount' => $this->price->getAmount(),
            'priceCurrency' => $this->price->getCurrency()->getCurrencyCode(),
        ];
    }
}
