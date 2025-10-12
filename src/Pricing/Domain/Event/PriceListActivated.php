<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

final readonly class PriceListActivated implements DomainEvent
{
    public function __construct(
        private string $priceListId,
        private DateTimeImmutable $occurredOn
    ) {
    }

    public function priceListId(): string
    {
        return $this->priceListId;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function toArray(): array
    {
        return [
            'price_list_id' => $this->priceListId,
            'occurred_on' => $this->occurredOn->format(DateTimeImmutable::ATOM),
        ];
    }
}
