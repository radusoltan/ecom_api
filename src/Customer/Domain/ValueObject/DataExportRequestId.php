<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Data Export Request ID Value Object.
 *
 * Unique identifier for data export requests using UUID v7 (time-ordered).
 */
final readonly class DataExportRequestId
{
    private function __construct(
        private string $value
    ) {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid data export request ID: "%s"', $value));
        }

        $uuid = Uuid::fromString($value);
        if ($uuid->toRfc4122() !== $value) {
            throw new \InvalidArgumentException(sprintf('Data export request ID must be a valid UUID: "%s"', $value));
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
