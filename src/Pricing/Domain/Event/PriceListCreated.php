<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

/**
 * Domain Event: PriceList was created.
 */
final readonly class PriceListCreated implements DomainEvent
{
    public function __construct(
        private string $priceListId,
        private string $tenantId,
        private string $name,
        private int $priority,
        private \DateTimeImmutable $occurredOn
    ) {
    }

    public function priceListId(): string
    {
        return $this->priceListId;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function toArray(): array
    {
        return [
            'price_list_id' => $this->priceListId,
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'priority' => $this->priority,
            'occurred_on' => $this->occurredOn->format(\DateTimeImmutable::ATOM),
        ];
    }
}
