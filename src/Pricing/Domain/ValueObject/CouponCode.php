<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

/**
 * Coupon code value object.
 * Pattern: 4-20 uppercase alphanumeric characters.
 */
final readonly class CouponCode
{
    private const MIN_LENGTH = 4;
    private const MAX_LENGTH = 20;
    private const PATTERN = '/^[A-Z0-9]+$/';

    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        $uppercase = strtoupper($trimmed);

        if (strlen($uppercase) < self::MIN_LENGTH || strlen($uppercase) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('CouponCode must be between %d and %d characters, got: %d', self::MIN_LENGTH, self::MAX_LENGTH, strlen($uppercase)));
        }

        if (!preg_match(self::PATTERN, $uppercase)) {
            throw new \InvalidArgumentException(sprintf('CouponCode must contain only uppercase alphanumeric characters, got: "%s"', $uppercase));
        }

        return new self($uppercase);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
