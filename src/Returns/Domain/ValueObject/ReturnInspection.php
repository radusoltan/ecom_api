<?php

declare(strict_types=1);

namespace App\Returns\Domain\ValueObject;

/**
 * ReturnInspection Value Object.
 *
 * Encapsulates the inspection details of a returned item.
 *
 * Business Rules:
 * - Inspection notes must be at least 10 characters
 * - Inspection notes cannot exceed 2000 characters
 * - Resellable flag must be explicitly set
 * - Notes cannot be empty or whitespace-only
 */
final readonly class ReturnInspection
{
    private const MIN_NOTES_LENGTH = 10;
    private const MAX_NOTES_LENGTH = 2000;

    private function __construct(
        private bool $isResellable,
        private string $notes,
        private \DateTimeImmutable $inspectedAt,
    ) {
    }

    /**
     * Create a new inspection result.
     *
     * @param bool                    $isResellable Whether the item can be resold
     * @param string                  $notes        Inspector's notes about the item condition
     * @param \DateTimeImmutable|null $inspectedAt  Timestamp of inspection (defaults to now)
     *
     * @throws \InvalidArgumentException If notes are invalid
     */
    public static function create(
        bool $isResellable,
        string $notes,
        ?\DateTimeImmutable $inspectedAt = null,
    ): self {
        $trimmedNotes = trim($notes);

        if (empty($trimmedNotes)) {
            throw new \InvalidArgumentException('Inspection notes cannot be empty.');
        }

        if (mb_strlen($trimmedNotes) < self::MIN_NOTES_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Inspection notes must be at least %d characters long. Got: %d', self::MIN_NOTES_LENGTH, mb_strlen($trimmedNotes)));
        }

        if (mb_strlen($trimmedNotes) > self::MAX_NOTES_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Inspection notes cannot exceed %d characters. Got: %d', self::MAX_NOTES_LENGTH, mb_strlen($trimmedNotes)));
        }

        return new self(
            isResellable: $isResellable,
            notes: $trimmedNotes,
            inspectedAt: $inspectedAt ?? new \DateTimeImmutable()
        );
    }

    public function isResellable(): bool
    {
        return $this->isResellable;
    }

    public function notes(): string
    {
        return $this->notes;
    }

    public function inspectedAt(): \DateTimeImmutable
    {
        return $this->inspectedAt;
    }

    /**
     * Check if the inspection result indicates the item is damaged.
     */
    public function isDamaged(): bool
    {
        return !$this->isResellable;
    }

    /**
     * Check if inspection occurred within a given timeframe.
     *
     * @param \DateTimeImmutable $since Start of timeframe
     * @param \DateTimeImmutable $until End of timeframe
     */
    public function wasInspectedBetween(\DateTimeImmutable $since, \DateTimeImmutable $until): bool
    {
        return $this->inspectedAt >= $since && $this->inspectedAt <= $until;
    }

    /**
     * Get the age of the inspection in days.
     */
    public function getAgeInDays(\DateTimeImmutable $now = new \DateTimeImmutable()): int
    {
        $interval = $this->inspectedAt->diff($now);

        return (int) $interval->format('%a');
    }

    public function equals(self $other): bool
    {
        return $this->isResellable === $other->isResellable
            && $this->notes === $other->notes
            && $this->inspectedAt == $other->inspectedAt;
    }
}
