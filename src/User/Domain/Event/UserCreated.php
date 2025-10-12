<?php

declare(strict_types=1);

namespace App\User\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\User\Domain\ValueObject\UserId;
use DateTimeImmutable;

final readonly class UserCreated implements DomainEvent
{
    public function __construct(
        private UserId $userId,
        private string $email,
        private string $username,
        private DateTimeImmutable $occurredOn
    ) {
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
