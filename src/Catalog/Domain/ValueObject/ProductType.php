<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * ProductType Value Object
 *
 * Represents the type/structure of a product in the catalog.
 *
 * Business Rules (from PRD):
 * - SIMPLE: Single product with no variations (e.g., book, single item)
 * - CONFIGURABLE: Product with selectable options/variants (e.g., t-shirt with size/color)
 * - BUNDLE: Collection of products sold together (e.g., gift sets)
 * - VIRTUAL: Non-physical product (e.g., software license, digital download)
 * - SUBSCRIPTION: Recurring product (e.g., monthly subscription box)
 *
 * Type changes are restricted after first sale to maintain data integrity.
 */
final readonly class ProductType
{
    private const SIMPLE = 'simple';
    private const CONFIGURABLE = 'configurable';
    private const BUNDLE = 'bundle';
    private const VIRTUAL = 'virtual';
    private const SUBSCRIPTION = 'subscription';

    private const VALID_TYPES = [
        self::SIMPLE,
        self::CONFIGURABLE,
        self::BUNDLE,
        self::VIRTUAL,
        self::SUBSCRIPTION,
    ];

    private function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid product type "%s". Valid types are: %s',
                    $value,
                    implode(', ', self::VALID_TYPES)
                )
            );
        }
    }

    public static function simple(): self
    {
        return new self(self::SIMPLE);
    }

    public static function configurable(): self
    {
        return new self(self::CONFIGURABLE);
    }

    public static function bundle(): self
    {
        return new self(self::BUNDLE);
    }

    public static function virtual(): self
    {
        return new self(self::VIRTUAL);
    }

    public static function subscription(): self
    {
        return new self(self::SUBSCRIPTION);
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower(trim($value)));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isSimple(): bool
    {
        return $this->value === self::SIMPLE;
    }

    public function isConfigurable(): bool
    {
        return $this->value === self::CONFIGURABLE;
    }

    public function isBundle(): bool
    {
        return $this->value === self::BUNDLE;
    }

    public function isVirtual(): bool
    {
        return $this->value === self::VIRTUAL;
    }

    public function isSubscription(): bool
    {
        return $this->value === self::SUBSCRIPTION;
    }

    /**
     * Check if the product type requires physical shipping
     */
    public function requiresShipping(): bool
    {
        return !$this->isVirtual() && !$this->isSubscription();
    }

    /**
     * Check if the product type supports variants/options
     */
    public function supportsVariants(): bool
    {
        return $this->isConfigurable();
    }

    /**
     * Check if the product type can contain other products
     */
    public function isComposite(): bool
    {
        return $this->isBundle();
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
