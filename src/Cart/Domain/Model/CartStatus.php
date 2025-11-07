<?php

declare(strict_types=1);

namespace App\Cart\Domain\Model;

/**
 * CartStatus Enum.
 *
 * Status values:
 * - Active: Cart is being used
 * - Expired: Cart hasn't been updated in 7 days
 * - Converted: Cart was converted to an order
 */
enum CartStatus: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CONVERTED = 'converted';

    public static function active(): self
    {
        return self::ACTIVE;
    }

    public static function expired(): self
    {
        return self::EXPIRED;
    }

    public static function converted(): self
    {
        return self::CONVERTED;
    }

    public function isActive(): bool
    {
        return self::ACTIVE === $this;
    }

    public function isExpired(): bool
    {
        return self::EXPIRED === $this;
    }

    public function isConverted(): bool
    {
        return self::CONVERTED === $this;
    }

    public function value(): string
    {
        return $this->value;
    }
}
