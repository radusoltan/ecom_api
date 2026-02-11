<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Model;

use Symfony\Component\Uid\Ulid;

/**
 * NotificationId Value Object (ULID-based).
 */
final readonly class NotificationId implements \Stringable
{
    private function __construct(private string $value)
    {
        if ('' === $value || '0' === $value) {
            throw new \InvalidArgumentException('NotificationId cannot be empty');
        }

        // Validate ULID format (26 characters, base32)
        if (!preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i', $value)) {
            throw new \InvalidArgumentException(sprintf('Invalid NotificationId format: "%s". Must be a valid ULID', $value));
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

    public function equals(NotificationId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
