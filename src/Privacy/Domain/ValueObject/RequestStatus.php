<?php

declare(strict_types=1);

namespace App\Privacy\Domain\ValueObject;

/**
 * Data Subject Request Status Value Object.
 *
 * GDPR Compliance: Requests must be processed within 30 days (Article 12)
 *
 * Status flow:
 * PENDING → UNDER_REVIEW → [COMPLETED | REJECTED]
 */
final readonly class RequestStatus implements \Stringable
{
    private const PENDING = 'pending';
    private const UNDER_REVIEW = 'under_review';
    private const COMPLETED = 'completed';
    private const REJECTED = 'rejected';

    private const VALID_STATUSES = [
        self::PENDING,
        self::UNDER_REVIEW,
        self::COMPLETED,
        self::REJECTED,
    ];

    private const VALID_TRANSITIONS = [
        self::PENDING => [self::UNDER_REVIEW, self::REJECTED],
        self::UNDER_REVIEW => [self::COMPLETED, self::REJECTED],
        self::COMPLETED => [],
        self::REJECTED => [],
    ];

    private function __construct(private string $value)
    {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid request status: "%s". Valid statuses: %s', $value, implode(', ', self::VALID_STATUSES)));
        }
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function underReview(): self
    {
        return new self(self::UNDER_REVIEW);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function rejected(): self
    {
        return new self(self::REJECTED);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus->value, self::VALID_TRANSITIONS[$this->value], true);
    }

    public function isFinal(): bool
    {
        return in_array($this->value, [self::COMPLETED, self::REJECTED], true);
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
