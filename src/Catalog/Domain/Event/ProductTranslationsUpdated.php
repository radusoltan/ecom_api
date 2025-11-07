<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\Locale;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

final class ProductTranslationsUpdated implements DomainEvent
{
    private \DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly ProductId $productId,
        private readonly TenantId $tenantId,
        private readonly Locale $locale
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function locale(): Locale
    {
        return $this->locale;
    }

    public function getAggregateId(): string
    {
        return $this->productId->toString();
    }

    public function getEventName(): string
    {
        return 'catalog.product.translations_updated';
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId->toString(),
            'tenant_id' => $this->tenantId->toString(),
            'locale' => $this->locale->toString(),
            'occurred_at' => $this->occurredOn->format('c'),
        ];
    }
}
