<?php

declare(strict_types=1);

namespace App\Tax\Domain\Model;

use Symfony\Component\Uid\Uuid;

/**
 * Tax Rule ID Value Object.
 *
 * Unique identifier for tax rules using UUID v4.
 *
 * Business Rules:
 * - Must be a valid UUID v4 format
 * - Immutable once created
 * - Used for uniquely identifying tax rules across the system
 */
final readonly class TaxRuleId
{
    private function __construct(
        private string $value
    ) {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid TaxRuleId: "%s"', $value));
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::v4()->toString());
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
}
