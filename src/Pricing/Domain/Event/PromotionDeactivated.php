<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Event;

use App\Pricing\Domain\ValueObject\PromotionId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class PromotionDeactivated implements DomainEvent
{
    public function __construct(
        private PromotionId $promotionId,
        private TenantId $tenantId,
        private \DateTimeImmutable $occurredAt
    ) {
    }

    public function promotionId(): PromotionId
    {
        return $this->promotionId;
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
