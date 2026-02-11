<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Loyalty Program ID Value Object.
 *
 * Unique identifier for loyalty programs using UUID v7 (time-ordered).
 */
final readonly class LoyaltyProgramId
{
    private function __construct(
        private string $value
    ) {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid loyalty program ID: "%s"', $value));
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::v7()->toRfc4122());
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

    public function __toString(): string
    {
        return $this->value;
    }
}
