<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class AlertResolved implements DomainEvent
{
    public function __construct(
        public string $metric,
        public string $severity,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
