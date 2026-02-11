<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Model;

/**
 * NotificationStatus Enum.
 *
 * Tracks the lifecycle of a notification.
 */
enum NotificationStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function isPending(): bool
    {
        return self::PENDING === $this;
    }

    public function isSent(): bool
    {
        return self::SENT === $this;
    }

    public function isFailed(): bool
    {
        return self::FAILED === $this;
    }

    public function isCancelled(): bool
    {
        return self::CANCELLED === $this;
    }

    public function isTerminal(): bool
    {
        return $this->isSent() || $this->isCancelled();
    }

    public function canRetry(): bool
    {
        return $this->isFailed();
    }
}
