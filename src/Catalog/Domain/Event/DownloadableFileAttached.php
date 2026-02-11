<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class DownloadableFileAttached implements DomainEvent
{
    public function __construct(
        private ProductId $productId,
        private TenantId $tenantId,
        private string $filename,
        private \DateTimeImmutable $occurredOn
    ) {
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'catalog.downloadable_file.attached';
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId->toString(),
            'tenantId' => $this->tenantId->toString(),
            'filename' => $this->filename,
            'occurredOn' => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
