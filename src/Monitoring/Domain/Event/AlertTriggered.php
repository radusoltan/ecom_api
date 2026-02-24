<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class AlertTriggered implements DomainEvent
{
    public function __construct(
        public string $metric,
        public float $value,
        public string $severity,
        public string $message,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
