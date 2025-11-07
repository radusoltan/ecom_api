<?php

declare(strict_types=1);

namespace App\Tenant\Domain\ValueObject;

use Stringable;
use InvalidArgumentException;

final readonly class TenantStatus implements Stringable
{
    private const STATUS_ACTIVE = 'active';
    private const STATUS_INACTIVE = 'inactive';
    private const STATUS_SUSPENDED = 'suspended';

    private function __construct(private string $value)
    {
        if (!in_array($value, [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_SUSPENDED], true)) {
            throw new InvalidArgumentException(sprintf('Invalid tenant status: "%s". Must be "active", "inactive", or "suspended"', $value));
        }
    }

    public static function active(): self
    {
        return new self(self::STATUS_ACTIVE);
    }

    public static function inactive(): self
    {
        return new self(self::STATUS_INACTIVE);
    }

    public static function suspended(): self
    {
        return new self(self::STATUS_SUSPENDED);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function isActive(): bool
    {
        return self::STATUS_ACTIVE === $this->value;
    }

    public function isInactive(): bool
    {
        return self::STATUS_INACTIVE === $this->value;
    }

    public function isSuspended(): bool
    {
        return self::STATUS_SUSPENDED === $this->value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(TenantStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
