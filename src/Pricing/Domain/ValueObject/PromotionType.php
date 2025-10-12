<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

/**
 * Promotion types:
 * - cart_rule: Applied to entire cart (e.g., "10% off orders over $100")
 * - catalog_rule: Applied to specific products/categories (e.g., "20% off Electronics")
 * - coupon: Requires coupon code (e.g., "SAVE20")
 */
final readonly class PromotionType
{
    public const CART_RULE = 'cart_rule';
    public const CATALOG_RULE = 'catalog_rule';
    public const COUPON = 'coupon';

    private const VALID_TYPES = [
        self::CART_RULE,
        self::CATALOG_RULE,
        self::COUPON,
    ];

    private function __construct(private string $value)
    {
        if (!in_array($this->value, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid PromotionType: "%s". Must be one of: %s',
                    $this->value,
                    implode(', ', self::VALID_TYPES)
                )
            );
        }
    }

    public static function cartRule(): self
    {
        return new self(self::CART_RULE);
    }

    public static function catalogRule(): self
    {
        return new self(self::CATALOG_RULE);
    }

    public static function coupon(): self
    {
        return new self(self::COUPON);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function isCartRule(): bool
    {
        return $this->value === self::CART_RULE;
    }

    public function isCatalogRule(): bool
    {
        return $this->value === self::CATALOG_RULE;
    }

    public function isCoupon(): bool
    {
        return $this->value === self::COUPON;
    }
}
