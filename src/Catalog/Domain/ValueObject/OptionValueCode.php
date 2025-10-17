<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * Value object for option value code (e.g., "red", "xl", "cotton")
 * Used as identifier for specific option values
 *
 * Business Rules:
 * - Code must be lowercase alphanumeric with underscores or hyphens
 * - Pattern: ^[a-z0-9][a-z0-9_-]{0,31}$
 * - Max length: 32 characters
 * - Must start with a letter or number
 */
final readonly class OptionValueCode
{
    private const PATTERN = '/^[a-z0-9][a-z0-9_-]{0,31}$/';
    private const MAX_LENGTH = 32;

    private function __construct(
        private string $value
    ) {
        $this->validate($value);
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower(trim($value)));
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function value(): string
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

    private function validate(string $value): void
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Option value code cannot be empty');
        }

        if (strlen($value) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Option value code cannot exceed %d characters', self::MAX_LENGTH)
            );
        }

        if (!preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid option value code: %s. Must be lowercase alphanumeric with underscores or hyphens (e.g., "red", "xl", "navy-blue")',
                    $value
                )
            );
        }
    }
}
