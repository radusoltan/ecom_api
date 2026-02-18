<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\DownloadableFile;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class DownloadableFileUpdated implements DomainEvent
{
    public function __construct(
        private ProductId $productId,
        private TenantId $tenantId,
        private DownloadableFile $oldFile,
        private DownloadableFile $newFile,
        private \DateTimeImmutable $occurredOn,
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

    public function oldFile(): DownloadableFile
    {
        return $this->oldFile;
    }

    public function newFile(): DownloadableFile
    {
        return $this->newFile;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'catalog.downloadable_file.updated';
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId->toString(),
            'tenantId' => $this->tenantId->toString(),
            'oldFilename' => $this->oldFile->filename(),
            'newFilename' => $this->newFile->filename(),
            'occurredOn' => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
