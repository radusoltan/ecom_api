<?php

declare(strict_types=1);

namespace App\Review\Domain\Event;

use App\Review\Domain\Model\ReviewId;
use App\Shared\Domain\Event\DomainEvent;

final readonly class ReviewApproved implements DomainEvent
{
    public function __construct(
        public ReviewId $reviewId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
