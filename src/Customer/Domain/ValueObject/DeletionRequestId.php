<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Deletion Request ID Value Object.
 *
 * Unique identifier for account deletion requests using UUID v7.
 */
final readonly class DeletionRequestId
{
    private function __construct(
        private string $value,
    ) {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid deletion request ID: "%s"', $value));
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
