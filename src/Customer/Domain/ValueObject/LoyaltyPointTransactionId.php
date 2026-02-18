<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Loyalty Point Transaction ID Value Object.
 *
 * Unique identifier for loyalty point transactions using UUID v7.
 * UUID v7 is time-ordered, making it ideal for sequential transaction records.
 */
final readonly class LoyaltyPointTransactionId
{
    private function __construct(
        private string $value,
    ) {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid loyalty point transaction ID: "%s"', $value));
        }

        $uuid = Uuid::fromString($value);
        if ($uuid->toRfc4122() !== $value) {
            throw new \InvalidArgumentException(sprintf('Loyalty point transaction ID must be a valid UUID: "%s"', $value));
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
