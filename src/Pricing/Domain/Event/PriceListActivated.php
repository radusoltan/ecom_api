<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class PriceListActivated implements DomainEvent
{
    public function __construct(
        private string $priceListId,
        private string $tenantId,
        private \DateTimeImmutable $occurredOn,
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

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function toArray(): array
    {
        return [
            'price_list_id' => $this->priceListId,
            'tenant_id' => $this->tenantId,
            'occurred_on' => $this->occurredOn->format(\DateTimeImmutable::ATOM),
        ];
    }
}
