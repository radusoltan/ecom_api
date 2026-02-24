<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class ThresholdBreached implements DomainEvent
{
    public function __construct(
        public string $metric,
        public float $currentValue,
        public float $thresholdValue,
        public string $severity,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
