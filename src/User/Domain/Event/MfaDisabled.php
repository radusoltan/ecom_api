<?php

declare(strict_types=1);

namespace App\User\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\User\Domain\ValueObject\UserId;

final readonly class MfaDisabled implements DomainEvent
{
    public function __construct(
        private UserId $userId,
        private \DateTimeImmutable $occurredOn,
    ) {
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
