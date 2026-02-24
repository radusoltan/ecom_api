<?php

declare(strict_types=1);

namespace App\User\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\User\Domain\ValueObject\UserId;

final readonly class MfaBackupCodeConsumed implements DomainEvent
{
    public function __construct(
        private UserId $userId,
        private int $remainingCodes,
        private \DateTimeImmutable $occurredOn,
    ) {
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function remainingCodes(): int
    {
        return $this->remainingCodes;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
