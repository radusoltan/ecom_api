<?php

declare(strict_types=1);

namespace App\Media\Domain\ValueObject;

final readonly class MimeType
{
    private const ALLOWED = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/avif',
        'image/gif',
    ];

    private function __construct(
        private string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Mime type cannot be empty.');
        }

        if (!in_array($trimmed, self::ALLOWED, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported image mime type "%s".', $value));
        }

        return new self($trimmed);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(MimeType $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
