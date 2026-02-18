<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;

/**
 * Value Object representing a pricing rule with conditions and discount.
 *
 * Business Rules:
 * - scope: 'product', 'category', 'all'
 * - min_quantity: >= 1
 * - discount: required
 * - product_id: required for 'product' scope
 */
final readonly class PricingRule
{
    public const SCOPE_PRODUCT = 'product';
    public const SCOPE_CATEGORY = 'category';
    public const SCOPE_ALL = 'all';

    private function __construct(
        private string $scope,
        private Discount $discount,
        private ?ProductId $productId,
        private ?string $categoryId,
        private int $minQuantity,
        private ?Money $minPurchaseAmount,
    ) {
        if (!in_array($this->scope, [self::SCOPE_PRODUCT, self::SCOPE_CATEGORY, self::SCOPE_ALL], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid pricing rule scope "%s"', $this->scope));
        }

        if (self::SCOPE_PRODUCT === $this->scope && null === $this->productId) {
            throw new \InvalidArgumentException('Product scope requires a product ID');
        }

        if (self::SCOPE_CATEGORY === $this->scope && null === $this->categoryId) {
            throw new \InvalidArgumentException('Category scope requires a category ID');
        }

        if ($this->minQuantity < 1) {
            throw new \InvalidArgumentException(sprintf('Minimum quantity must be at least 1, got %d', $this->minQuantity));
        }

        if (null !== $this->minPurchaseAmount && $this->minPurchaseAmount->isNegative()) {
            throw new \InvalidArgumentException('Minimum purchase amount cannot be negative');
        }
    }

    public static function forProduct(
        ProductId $productId,
        Discount $discount,
        int $minQuantity = 1,
        ?Money $minPurchaseAmount = null,
    ): self {
        return new self(
            self::SCOPE_PRODUCT,
            $discount,
            $productId,
            null,
            $minQuantity,
            $minPurchaseAmount
        );
    }

    public static function forCategory(
        string $categoryId,
        Discount $discount,
        int $minQuantity = 1,
        ?Money $minPurchaseAmount = null,
    ): self {
        return new self(
            self::SCOPE_CATEGORY,
            $discount,
            null,
            $categoryId,
            $minQuantity,
            $minPurchaseAmount
        );
    }

    public static function forAll(
        Discount $discount,
        int $minQuantity = 1,
        ?Money $minPurchaseAmount = null,
    ): self {
        return new self(
            self::SCOPE_ALL,
            $discount,
            null,
            null,
            $minQuantity,
            $minPurchaseAmount
        );
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getDiscount(): Discount
    {
        return $this->discount;
    }

    public function getProductId(): ?ProductId
    {
        return $this->productId;
    }

    public function getCategoryId(): ?string
    {
        return $this->categoryId;
    }

    public function getMinQuantity(): int
    {
        return $this->minQuantity;
    }

    public function getMinPurchaseAmount(): ?Money
    {
        return $this->minPurchaseAmount;
    }

    /**
     * Check if this rule applies to a given product, quantity, and amount.
     */
    public function appliesTo(
        ProductId $productId,
        ?string $categoryId,
        int $quantity,
        Money $amount,
    ): bool {
        // Check scope match
        if (self::SCOPE_PRODUCT === $this->scope && !$this->productId->equals($productId)) {
            return false;
        }

        if (self::SCOPE_CATEGORY === $this->scope && $this->categoryId !== $categoryId) {
            return false;
        }

        // Check quantity threshold
        if ($quantity < $this->minQuantity) {
            return false;
        }

        // Check minimum purchase amount
        if (null !== $this->minPurchaseAmount && $amount->isLessThan($this->minPurchaseAmount)) {
            return false;
        }

        return true;
    }

    public function toArray(): array
    {
        return [
            'scope' => $this->scope,
            'discount' => $this->discount->toArray(),
            'product_id' => $this->productId?->toString(),
            'category_id' => $this->categoryId,
            'min_quantity' => $this->minQuantity,
            'min_purchase_amount' => $this->minPurchaseAmount?->toArray(),
        ];
    }
}
