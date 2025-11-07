<?php

declare(strict_types=1);

namespace App\User\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\UserRole;

final readonly class UserRoleAdded implements DomainEvent
{
    public function __construct(
        private UserId $userId,
        private UserRole $role,
        private \DateTimeImmutable $occurredOn
    ) {
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function role(): UserRole
    {
        return $this->role;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
