<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Consent History ID Value Object.
 *
 * UUID v7 identifier for consent history records.
 * UUID v7 includes timestamp ordering for better database performance and audit trail clarity.
 */
final readonly class ConsentHistoryId
{
    private function __construct(private string $value)
    {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid UUID format: "%s"', $value));
        }
    }

    /**
     * Generate new consent history ID (UUID v7 - timestamp-ordered).
     */
    public static function generate(): self
    {
        return new self(Uuid::v7()->toRfc4122());
    }

    /**
     * Create from existing string value.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Get string representation.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Check equality with another ID.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
