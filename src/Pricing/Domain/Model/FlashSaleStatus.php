<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

/**
 * Flash Sale Status Enum.
 *
 * Represents the lifecycle of a flash sale:
 * - scheduled: Created but not yet started
 * - active: Currently running
 * - ended: Time period has expired
 * - cancelled: Manually cancelled by admin
 */
enum FlashSaleStatus: string
{
    case SCHEDULED = 'scheduled';
    case ACTIVE = 'active';
    case ENDED = 'ended';
    case CANCELLED = 'cancelled';

    public function isScheduled(): bool
    {
        return $this === self::SCHEDULED;
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isEnded(): bool
    {
        return $this === self::ENDED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    public function canBeCancelled(): bool
    {
        return $this === self::SCHEDULED;
    }

    public function canBeActivated(): bool
    {
        return $this === self::SCHEDULED;
    }

    public function canBeEnded(): bool
    {
        return $this === self::ACTIVE;
    }
}
