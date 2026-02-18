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
        return self::SCHEDULED === $this;
    }

    public function isActive(): bool
    {
        return self::ACTIVE === $this;
    }

    public function isEnded(): bool
    {
        return self::ENDED === $this;
    }

    public function isCancelled(): bool
    {
        return self::CANCELLED === $this;
    }

    public function canBeCancelled(): bool
    {
        return self::SCHEDULED === $this;
    }

    public function canBeActivated(): bool
    {
        return self::SCHEDULED === $this;
    }

    public function canBeEnded(): bool
    {
        return self::ACTIVE === $this;
    }
}
