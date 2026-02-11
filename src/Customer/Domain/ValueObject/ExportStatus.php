<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

/**
 * Export Status Enum.
 *
 * Represents the lifecycle of a data export request.
 *
 * State Transitions:
 * - PENDING -> PROCESSING (when export generation starts)
 * - PROCESSING -> READY (when export file is generated successfully)
 * - PROCESSING -> FAILED (when export generation fails)
 * - READY -> EXPIRED (when download window expires after 24h)
 * - PENDING -> FAILED (if validation fails before processing)
 *
 * Business Rules:
 * - PENDING: Initial state, waiting to be processed
 * - PROCESSING: Currently generating export file
 * - READY: File is ready for download
 * - EXPIRED: Download window has passed (24h)
 * - FAILED: Export generation failed
 */
enum ExportStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case READY = 'ready';
    case EXPIRED = 'expired';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::READY => 'Ready',
            self::EXPIRED => 'Expired',
            self::FAILED => 'Failed',
        };
    }

    /**
     * Check if a state transition is valid.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PENDING => in_array($next, [self::PROCESSING, self::FAILED], true),
            self::PROCESSING => in_array($next, [self::READY, self::FAILED], true),
            self::READY => $next === self::EXPIRED,
            self::EXPIRED => false, // Terminal state
            self::FAILED => false, // Terminal state
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::EXPIRED, self::FAILED], true);
    }

    public function isDownloadable(): bool
    {
        return $this === self::READY;
    }
}
