<?php

declare(strict_types=1);

namespace App\Privacy\Domain\ValueObject;

use InvalidArgumentException;
use Stringable;
use Symfony\Component\Uid\Ulid;

final readonly class DataSubjectRequestId implements Stringable
{
    private function __construct(private string $value)
    {
        if (!Ulid::isValid($value)) {
            throw new InvalidArgumentException(sprintf('Invalid DataSubjectRequestId format: "%s"', $value));
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
