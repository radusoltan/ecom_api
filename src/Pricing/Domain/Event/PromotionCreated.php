<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Event;

use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class PromotionCreated implements DomainEvent
{
    public function __construct(
        private PromotionId $promotionId,
        private TenantId $tenantId,
        private string $name,
        private PromotionType $type,
        private \DateTimeImmutable $occurredAt,
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

    public function name(): string
    {
        return $this->name;
    }

    public function type(): PromotionType
    {
        return $this->type;
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
