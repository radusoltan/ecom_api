<?php

declare(strict_types=1);

namespace App\Internationalization\Domain\Model;

/**
 * TranslationKey Value Object.
 *
 * Represents a translation key (e.g., "common.save", "buttons.add_to_cart")
 */
final readonly class TranslationKey
{
    private const MAX_LENGTH = 255;
    private const MIN_LENGTH = 1;

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if (strlen($trimmed) < self::MIN_LENGTH) {
            throw new \InvalidArgumentException('Translation key cannot be empty');
        }

        if (strlen($trimmed) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Translation key cannot exceed %d characters', self::MAX_LENGTH));
        }

        // Validate key format (alphanumeric, dots, underscores, hyphens)
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $trimmed)) {
            throw new \InvalidArgumentException('Translation key must contain only alphanumeric characters, dots, underscores, and hyphens');
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function contains(string $substring): bool
    {
        return str_contains($this->value, $substring);
    }
}
