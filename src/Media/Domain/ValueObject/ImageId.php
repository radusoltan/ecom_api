<?php

declare(strict_types=1);

namespace App\Media\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class ImageId
{
    private function __construct(
        private string $value
    ) {}

    public static function generate(): self
    {
        return new self(Uuid::v7()->toString());
    }

    public static function fromString(string $value): self
    {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException('Invalid ImageId.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(ImageId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
