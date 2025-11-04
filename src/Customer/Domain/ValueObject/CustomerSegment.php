<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

/**
 * Customer Segment Value Object.
 *
 * Represents customer segmentation for targeted marketing and promotions.
 *
 * Business Rules:
 * - regular: Default segment for new customers (B2C)
 * - vip: High-value customers (lifetime value > $10,000 or loyalty points > 1000)
 * - wholesale: B2B customers (bulk purchases, separate pricing, tax exemptions)
 */
final readonly class CustomerSegment
{
    private const REGULAR = 'regular';
    private const VIP = 'vip';
    private const WHOLESALE = 'wholesale';
    private const PREMIUM = 'premium';

    private const VALID_SEGMENTS = [
        self::REGULAR,
        self::VIP,
        self::WHOLESALE,
        self::PREMIUM,
    ];

    private function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_SEGMENTS, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid customer segment: "%s". Allowed: %s', $value, implode(', ', self::VALID_SEGMENTS))
            );
        }
    }

    public static function regular(): self
    {
        return new self(self::REGULAR);
    }

    public static function vip(): self
    {
        return new self(self::VIP);
    }

    public static function wholesale(): self
    {
        return new self(self::WHOLESALE);
    }

    public static function premium(): self
    {
        return new self(self::PREMIUM);
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower($value));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function isRegular(): bool
    {
        return $this->value === self::REGULAR;
    }

    public function isVip(): bool
    {
        return $this->value === self::VIP;
    }

    public function isWholesale(): bool
    {
        return $this->value === self::WHOLESALE;
    }

    public function isPremium(): bool
    {
        return $this->value === self::PREMIUM;
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
