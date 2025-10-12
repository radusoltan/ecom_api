<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

final readonly class PricingRuleAdded implements DomainEvent
{
    /**
     * @param array<string, mixed> $ruleData
     */
    public function __construct(
        private string $priceListId,
        private array $ruleData
    ) {
    }

    public function priceListId(): string
    {
        return $this->priceListId;
    }

    /**
     * @return array<string, mixed>
     */
    public function ruleData(): array
    {
        return $this->ruleData;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'price_list_id' => $this->priceListId,
            'rule' => $this->ruleData,
            'occurred_on' => $this->occurredOn()->format(DateTimeImmutable::ATOM),
        ];
    }
}
