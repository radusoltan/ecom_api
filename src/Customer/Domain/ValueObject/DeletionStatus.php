<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

/**
 * Deletion Request Status Value Object.
 *
 * Represents the lifecycle of an account deletion request.
 */
enum DeletionStatus: string
{
    case PENDING = 'pending';           // Request created, awaiting confirmation
    case CONFIRMED = 'confirmed';       // Customer confirmed, 30-day countdown started
    case PROCESSING = 'processing';     // Deletion in progress
    case COMPLETED = 'completed';       // Deletion completed
    case CANCELLED = 'cancelled';       // Customer cancelled the request
    case ON_HOLD = 'on_hold';          // Admin put on legal/litigation hold

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Confirmation',
            self::CONFIRMED => 'Confirmed (Scheduled)',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::ON_HOLD => 'On Hold (Legal)',
        };
    }

    /**
     * Check if transition to another status is valid.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PENDING => in_array($next, [self::CONFIRMED, self::CANCELLED], true),
            self::CONFIRMED => in_array($next, [self::PROCESSING, self::CANCELLED, self::ON_HOLD], true),
            self::PROCESSING => in_array($next, [self::COMPLETED], true),
            self::ON_HOLD => in_array($next, [self::CONFIRMED, self::CANCELLED], true),
            self::COMPLETED, self::CANCELLED => false, // Terminal states
        };
    }

    public function isPending(): bool
    {
        return self::PENDING === $this;
    }

    public function isConfirmed(): bool
    {
        return self::CONFIRMED === $this;
    }

    public function isProcessing(): bool
    {
        return self::PROCESSING === $this;
    }

    public function isCompleted(): bool
    {
        return self::COMPLETED === $this;
    }

    public function isCancelled(): bool
    {
        return self::CANCELLED === $this;
    }

    public function isOnHold(): bool
    {
        return self::ON_HOLD === $this;
    }

    public function isTerminal(): bool
    {
        return self::COMPLETED === $this || self::CANCELLED === $this;
    }
}
