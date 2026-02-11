<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

/**
 * Address Type Enum.
 *
 * Defines the type of customer address.
 */
enum AddressType: string
{
    case SHIPPING = 'shipping';
    case BILLING = 'billing';
    case BOTH = 'both';

    public function toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'shipping' => self::SHIPPING,
            'billing' => self::BILLING,
            'both' => self::BOTH,
            default => throw new \InvalidArgumentException(sprintf('Invalid address type: "%s". Allowed: shipping, billing, both', $value)),
        };
    }

    public function isShipping(): bool
    {
        return self::SHIPPING === $this || self::BOTH === $this;
    }

    public function isBilling(): bool
    {
        return self::BILLING === $this || self::BOTH === $this;
    }
}
