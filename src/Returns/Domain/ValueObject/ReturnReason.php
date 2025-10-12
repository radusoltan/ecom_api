<?php

declare(strict_types=1);

namespace App\Returns\Domain\ValueObject;

/**
 * Value Object representing the reason for a return request.
 *
 * Business Rules:
 * - Reason is required (min 10 chars)
 * - Max length: 500 characters
 * - Trimmed and validated
 */
final readonly class ReturnReason
{
    private const MIN_LENGTH = 10;
    private const MAX_LENGTH = 500;

    private function __construct(
        private string $value
    ) {
        $trimmed = trim($this->value);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Return reason cannot be empty.');
        }

        $length = mb_strlen($trimmed);

        if ($length < self::MIN_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Return reason must be at least %d characters. Got %d.', self::MIN_LENGTH, $length)
            );
        }

        if ($length > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Return reason must not exceed %d characters. Got %d.', self::MAX_LENGTH, $length)
            );
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return trim($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value() === $other->value();
    }

    public function __toString(): string
    {
        return $this->value();
    }
}
