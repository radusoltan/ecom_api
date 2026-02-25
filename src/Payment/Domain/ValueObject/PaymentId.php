<?php

declare(strict_types=1);

namespace App\Payment\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Payment ID Value Object.
 *
 * Unique identifier for payment transactions using UUID v7 for:
 * - Sortable by creation time
 * - Native PostgreSQL uuid type support
 * - 128-bit unique identifier
 */
final readonly class PaymentId
{
    private function __construct(
        private string $value,
    ) {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid Payment ID format: "%s". Must be a valid UUID.', $value));
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
