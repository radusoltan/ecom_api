<?php

declare(strict_types=1);

namespace App\Privacy\Domain\ValueObject;

use InvalidArgumentException;
use Stringable;

/**
 * Data Subject Request Type Value Object
 *
 * GDPR Rights:
 * - ACCESS: Right to access (Article 15) - Export all personal data
 * - RECTIFICATION: Right to rectification (Article 16) - Correct inaccurate data
 * - ERASURE: Right to erasure/"Right to be forgotten" (Article 17) - Delete personal data
 * - PORTABILITY: Right to data portability (Article 20) - Machine-readable export
 * - RESTRICTION: Right to restriction of processing (Article 18) - Limit processing
 * - OBJECTION: Right to object (Article 21) - Object to processing
 */
final readonly class RequestType implements Stringable
{
    private const ACCESS = 'access';
    private const RECTIFICATION = 'rectification';
    private const ERASURE = 'erasure';
    private const PORTABILITY = 'portability';
    private const RESTRICTION = 'restriction';
    private const OBJECTION = 'objection';

    private const VALID_TYPES = [
        self::ACCESS,
        self::RECTIFICATION,
        self::ERASURE,
        self::PORTABILITY,
        self::RESTRICTION,
        self::OBJECTION,
    ];

    private function __construct(private string $value)
    {
        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid request type: "%s". Valid types: %s',
                    $value,
                    implode(', ', self::VALID_TYPES)
                )
            );
        }
    }

    public static function access(): self
    {
        return new self(self::ACCESS);
    }

    public static function rectification(): self
    {
        return new self(self::RECTIFICATION);
    }

    public static function erasure(): self
    {
        return new self(self::ERASURE);
    }

    public static function portability(): self
    {
        return new self(self::PORTABILITY);
    }

    public static function restriction(): self
    {
        return new self(self::RESTRICTION);
    }

    public static function objection(): self
    {
        return new self(self::OBJECTION);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isDestructive(): bool
    {
        return $this->value === self::ERASURE;
    }

    public function requiresManualReview(): bool
    {
        return in_array($this->value, [
            self::RECTIFICATION,
            self::RESTRICTION,
            self::OBJECTION,
        ], true);
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
