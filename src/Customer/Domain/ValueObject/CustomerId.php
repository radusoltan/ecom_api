<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Customer ID Value Object.
 *
 * Unique identifier for customers using UUID v4.
 */
final readonly class CustomerId
{
    private function __construct(
        private string $value
    ) {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid customer ID: "%s"', $value));
        }

        $uuid = Uuid::fromString($value);
        if ($uuid->toRfc4122() !== $value) {
            throw new \InvalidArgumentException(sprintf('Customer ID must be a valid UUID v4: "%s"', $value));
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::v4()->toRfc4122());
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
