<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Money;

/**
 * Bundle Value Object.
 *
 * Represents a collection of products sold together as a bundle.
 *
 * Business Rules (from PRD):
 * - Bundle must contain at least 1 item (min: 1, max: 20 per PRD)
 * - Discount percentage: 0-50% (inclusive)
 * - Bundle price = sum(item prices) × (1 - discount%)
 * - Cannot add bundle to bundle (circular reference check - handled in Product aggregate)
 * - Immutable once created
 */
final readonly class Bundle
{
    private const MIN_ITEMS = 1;
    private const MAX_ITEMS = 20;
    private const MIN_DISCOUNT = 0.0;
    private const MAX_DISCOUNT = 50.0;

    /**
     * @param array<BundleItem> $items
     */
    private function __construct(
        private array $items,
        private float $discountPercentage,
    ) {
        // Validate items count
        $itemCount = count($items);
        if ($itemCount < self::MIN_ITEMS) {
            throw new \InvalidArgumentException(sprintf('Bundle must contain at least %d item', self::MIN_ITEMS));
        }

        if ($itemCount > self::MAX_ITEMS) {
            throw new \InvalidArgumentException(sprintf('Bundle cannot contain more than %d items (got %d)', self::MAX_ITEMS, $itemCount));
        }

        // Validate discount
        if ($discountPercentage < self::MIN_DISCOUNT || $discountPercentage > self::MAX_DISCOUNT) {
            throw new \InvalidArgumentException(sprintf('Bundle discount must be between %.1f%% and %.1f%% (got %.1f%%)', self::MIN_DISCOUNT, self::MAX_DISCOUNT, $discountPercentage));
        }
    }

    /**
     * @param array<BundleItem> $items
     */
    public static function create(
        array $items,
        float $discountPercentage = 0.0,
    ): self {
        return new self($items, $discountPercentage);
    }

    /**
     * @return array<BundleItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function discountPercentage(): float
    {
        return $this->discountPercentage;
    }

    public function itemCount(): int
    {
        return count($this->items);
    }

    /**
     * Calculate bundle price: sum(items) × (1 - discount%).
     *
     * Business Rule: Price calculated from individual item prices with discount applied
     */
    public function calculatePrice(): Money
    {
        // Sum all item totals
        $sum = Money::fromScalars(0, 'USD'); // Start with zero
        foreach ($this->items as $item) {
            $sum = $sum->add($item->totalPrice());
        }

        // Apply discount
        if ($this->discountPercentage > 0) {
            $discountMultiplier = 1 - ($this->discountPercentage / 100);
            $sum = $sum->multiplyBy($discountMultiplier);
        }

        return $sum;
    }

    /**
     * Calculate total savings from discount.
     */
    public function calculateSavings(): Money
    {
        if (0.0 === $this->discountPercentage) {
            return Money::fromScalars(0, 'USD');
        }

        $originalPrice = Money::fromScalars(0, 'USD');
        foreach ($this->items as $item) {
            $originalPrice = $originalPrice->add($item->totalPrice());
        }

        return $originalPrice->subtract($this->calculatePrice());
    }

    /**
     * Check if bundle contains a specific product.
     */
    public function containsProduct(string $productId): bool
    {
        foreach ($this->items as $item) {
            if ($item->productId()->toString() === $productId) {
                return true;
            }
        }

        return false;
    }

    public function equals(self $other): bool
    {
        if ($this->discountPercentage !== $other->discountPercentage) {
            return false;
        }

        if (count($this->items) !== count($other->items)) {
            return false;
        }

        // Compare all items
        foreach ($this->items as $index => $item) {
            if (!isset($other->items[$index]) || !$item->equals($other->items[$index])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert to array representation (for persistence/serialization).
     *
     * @return array{items: array<array{productId: string, quantity: int, priceAmount: int, priceCurrency: string}>, discountPercentage: float}
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(fn (BundleItem $item) => $item->toArray(), $this->items),
            'discountPercentage' => $this->discountPercentage,
        ];
    }
}
