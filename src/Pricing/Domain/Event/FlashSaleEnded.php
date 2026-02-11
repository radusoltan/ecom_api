<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Event;

use App\Pricing\Domain\Model\FlashSaleId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class FlashSaleEnded implements DomainEvent
{
    public function __construct(
        private FlashSaleId $flashSaleId,
        private TenantId $tenantId,
        private \DateTimeImmutable $occurredAt
    ) {
    }

    public function flashSaleId(): FlashSaleId
    {
        return $this->flashSaleId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
