<?php

declare(strict_types=1);

namespace App\Tax\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Tax Rule ID Value Object.
 *
 * Unique identifier for tax rules using UUID v7 format.
 */
final readonly class TaxRuleId
{
    private function __construct(
        private string $value,
    ) {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid TaxRuleId format: "%s". Must be a valid UUID', $value));
        }
    }

    public static function generate(): self
    {
        return new self((string) Uuid::v7());
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
