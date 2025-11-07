<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * Value object for variant SKU
 * Extends base SKU pattern with variant-specific rules.
 *
 * Business Rules:
 * - Variant SKU format: {BASE_SKU}-{VARIANT_SUFFIX}
 * - Base SKU: AAA-BBB-000000 (category-vendor-sequence)
 * - Variant suffix: lowercase alphanumeric with hyphens (e.g., "red-xl")
 * - Total max length: 64 characters
 * - Example: "CLO-NIK-000123-red-xl"
 */
final readonly class VariantSKU
{
    // Base SKU pattern from Product.SKU
    private const BASE_PATTERN = '/^[A-Z]{3}-[A-Z]{3}-[0-9]{6}$/';

    // Variant suffix pattern
    private const VARIANT_SUFFIX_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    // Full variant SKU pattern
    private const FULL_PATTERN = '/^[A-Z]{3}-[A-Z]{3}-[0-9]{6}-[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const MAX_LENGTH = 64;

    private function __construct(
        private string $value
    ) {
        $this->validate($value);
    }

    /**
     * Create from full SKU string.
     */
    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    /**
     * Create from base SKU and option value codes.
     *
     * @param string            $baseSku    Base product SKU (e.g., "CLO-NIK-000123")
     * @param OptionValueCode[] $valueCodes Array of option value codes in order
     */
    public static function generate(string $baseSku, array $valueCodes): self
    {
        if (empty($valueCodes)) {
            throw new \InvalidArgumentException('At least one option value code is required to generate variant SKU');
        }

        // Validate base SKU
        if (!preg_match(self::BASE_PATTERN, $baseSku)) {
            throw new \InvalidArgumentException(sprintf('Invalid base SKU format: %s. Expected format: AAA-BBB-000000', $baseSku));
        }

        // Build variant suffix from value codes
        $suffix = implode('-', array_map(
            fn (OptionValueCode $code) => $code->value(),
            $valueCodes
        ));

        $variantSku = sprintf('%s-%s', $baseSku, strtolower($suffix));

        return new self($variantSku);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Get the base SKU (without variant suffix).
     */
    public function getBaseSku(): string
    {
        // Split on hyphen and take first 3 parts (AAA-BBB-000000)
        $parts = explode('-', $this->value);
        if (count($parts) < 3) {
            throw new \RuntimeException('Invalid variant SKU structure');
        }

        return sprintf('%s-%s-%s', $parts[0], $parts[1], $parts[2]);
    }

    /**
     * Get the variant suffix (everything after base SKU).
     */
    public function getVariantSuffix(): string
    {
        $baseSku = $this->getBaseSku();

        return substr($this->value, strlen($baseSku) + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function validate(string $value): void
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Variant SKU cannot be empty');
        }

        if (strlen($value) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Variant SKU cannot exceed %d characters', self::MAX_LENGTH));
        }

        if (!preg_match(self::FULL_PATTERN, $value)) {
            throw new \InvalidArgumentException(sprintf('Invalid variant SKU format: %s. Expected format: AAA-BBB-000000-suffix (e.g., "CLO-NIK-000123-red-xl")', $value));
        }
    }
}
