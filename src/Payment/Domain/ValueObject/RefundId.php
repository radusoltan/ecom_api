<?php

declare(strict_types=1);

namespace App\Payment\Domain\ValueObject;

/**
 * Refund Identifier Value Object.
 *
 * Immutable UUID v4 wrapper for refund identification.
 * Used throughout the Payment bounded context for tracking refund operations.
 */
final readonly class RefundId implements \Stringable
{
    private function __construct(private string $value)
    {
        if ('' === $value || '0' === $value) {
            throw new \InvalidArgumentException('RefundId cannot be empty');
        }

        // Validate UUID v4 format
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            throw new \InvalidArgumentException(sprintf('Invalid RefundId format: "%s". Must be a valid UUID v4', $value));
        }
    }

    /**
     * Generate a new random RefundId.
     */
    public static function generate(): self
    {
        return new self(self::generateUuidV4());
    }

    /**
     * Create RefundId from an existing UUID string.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Get the RefundId as a string.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Check equality with another RefundId.
     */
    public function equals(RefundId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Generate a UUID v4 string.
     */
    private static function generateUuidV4(): string
    {
        $data = random_bytes(16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0F | 0x40);

        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
