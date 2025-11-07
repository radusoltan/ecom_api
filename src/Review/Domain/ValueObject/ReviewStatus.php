<?php

declare(strict_types=1);

namespace App\Review\Domain\ValueObject;

/**
 * Value object for review status.
 */
enum ReviewStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function isPending(): bool
    {
        return self::PENDING === $this;
    }

    public function isApproved(): bool
    {
        return self::APPROVED === $this;
    }

    public function isRejected(): bool
    {
        return self::REJECTED === $this;
    }
}
