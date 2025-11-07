<?php

declare(strict_types=1);

namespace App\Media\Domain\ValueObject;

final readonly class FilePath
{
    private function __construct(
        private string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException('File path cannot be empty.');
        }

        if ('/' !== $trimmed[0]) {
            throw new \InvalidArgumentException('File path must be absolute.');
        }

        return new self($trimmed);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(FilePath $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
