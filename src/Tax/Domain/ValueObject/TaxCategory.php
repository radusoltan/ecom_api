<?php

declare(strict_types=1);

namespace App\Tax\Domain\ValueObject;

/**
 * Tax Category Value Object.
 *
 * Represents product tax categories with different rates.
 * Examples: standard (full VAT), reduced (books, food), zero-rated (exports).
 *
 * Business Rules:
 * - Categories are predefined (not user-configurable)
 * - Each category maps to different rates per jurisdiction
 * - Used for product classification and automatic rate selection
 */
final readonly class TaxCategory
{
    public const STANDARD = 'standard';
    public const REDUCED = 'reduced';
    public const ZERO_RATED = 'zero_rated';
    public const EXEMPT = 'exempt';

    private function __construct(
        private string $value,
    ) {
        if (!in_array($value, self::allowedCategories(), true)) {
            throw new \InvalidArgumentException(sprintf('Invalid tax category "%s". Allowed: %s', $value, implode(', ', self::allowedCategories())));
        }
    }

    public static function standard(): self
    {
        return new self(self::STANDARD);
    }

    public static function reduced(): self
    {
        return new self(self::REDUCED);
    }

    public static function zeroRated(): self
    {
        return new self(self::ZERO_RATED);
    }

    public static function exempt(): self
    {
        return new self(self::EXEMPT);
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower($value));
    }

    /**
     * @return array<string>
     */
    private static function allowedCategories(): array
    {
        return [
            self::STANDARD,
            self::REDUCED,
            self::ZERO_RATED,
            self::EXEMPT,
        ];
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isStandard(): bool
    {
        return self::STANDARD === $this->value;
    }

    public function isReduced(): bool
    {
        return self::REDUCED === $this->value;
    }

    public function isZeroRated(): bool
    {
        return self::ZERO_RATED === $this->value;
    }

    public function isExempt(): bool
    {
        return self::EXEMPT === $this->value;
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
