<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class FlashSaleActivated implements DomainEvent
{
    /**
     * @param array<ProductId> $productIds
     */
    public function __construct(
        private FlashSaleId $flashSaleId,
        private TenantId $tenantId,
        private string $name,
        private array $productIds,
        private \DateTimeImmutable $occurredAt,
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

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<ProductId>
     */
    public function productIds(): array
    {
        return $this->productIds;
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
