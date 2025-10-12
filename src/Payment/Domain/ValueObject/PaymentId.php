<?php

declare(strict_types=1);

namespace App\Payment\Domain\ValueObject;

use Symfony\Component\Uid\Ulid;

/**
 * Payment ID Value Object
 *
 * Unique identifier for payment transactions using ULID for:
 * - Sortable by creation time
 * - URL-safe Base32 encoding
 * - 128-bit unique identifier
 */
final readonly class PaymentId
{
    private function __construct(
        private string $value
    ) {
        if (!Ulid::isValid($value)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid Payment ID format: "%s". Must be a valid ULID.', $value)
            );
        }
    }

    public static function generate(): self
    {
        return new self((string) new Ulid());
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
