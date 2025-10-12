<?php

declare(strict_types=1);

namespace App\Shared\Domain\Aggregate;

abstract class AggregateRoot
{
    /** @var array<object> */
    private array $events = [];

    protected function recordEvent(object $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return array<object>
     */
    public function popEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    /**
     * Alias for popEvents() for backward compatibility
     * @return array<object>
     */
    public function pullDomainEvents(): array
    {
        return $this->popEvents();
    }

    protected function clearEvents(): void
    {
        $this->events = [];
    }

    public function hasEvents(): bool
    {
        return count($this->events) > 0;
    }
}
