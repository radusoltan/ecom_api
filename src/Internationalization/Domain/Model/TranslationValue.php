<?php

declare(strict_types=1);

namespace App\Internationalization\Domain\Model;

/**
 * TranslationValue Value Object.
 *
 * Represents a translation value (the actual translated text)
 */
final readonly class TranslationValue
{
    private const MAX_LENGTH = 10000; // Support long texts (e.g., email templates)

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (strlen($value) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Translation value cannot exceed %d characters', self::MAX_LENGTH));
        }

        return new self($value);
    }

    public static function empty(): self
    {
        return new self('');
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return '' === trim($this->value);
    }

    public function length(): int
    {
        return strlen($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
