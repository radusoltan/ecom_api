<?php

declare(strict_types=1);

namespace App\User\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class UserId implements \Stringable
{
    private function __construct(
        private string $value
    ) {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid UUID: "%s"', $value));
        }

        // Ensure it's a v4 UUID
        $uuid = Uuid::fromString($value);
        if ($uuid->toRfc4122() !== $value) {
            throw new \InvalidArgumentException('User ID must be a valid UUID v4');
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

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
