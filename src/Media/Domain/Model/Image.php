<?php

declare(strict_types=1);

namespace App\Media\Domain\Model;

use App\Media\Domain\ValueObject\CropArea;
use App\Media\Domain\ValueObject\FilePath;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Domain\ValueObject\MimeType;
use App\Media\Domain\ValueObject\OwnerReference;
use App\Media\Domain\ValueObject\SizeLabel;
use App\Media\Domain\ValueObject\ThumbnailId;
use App\Shared\Domain\ValueObject\TenantId;

final class Image
{
    /** @var array<string, Thumbnail> */
    private array $thumbnails = [];

    private function __construct(
        private readonly ImageId $id,
        private TenantId $tenantId,
        private OwnerReference $owner,
        private MimeType $mimeType,
        private int $fileSize,
        private FilePath $originalPath,
        private ?string $title,
        private ?string $altText,
        private \DateTimeImmutable $uploadedAt
    ) {
    }

    public static function upload(
        TenantId $tenantId,
        OwnerReference $owner,
        MimeType $mimeType,
        int $fileSize,
        FilePath $originalPath,
        ?string $title = null,
        ?string $altText = null,
        ?\DateTimeImmutable $uploadedAt = null,
        ?ImageId $id = null
    ): self {
        if ($fileSize <= 0) {
            throw new \InvalidArgumentException('File size must be greater than zero.');
        }

        return new self(
            $id ?? ImageId::generate(),
            $tenantId,
            $owner,
            $mimeType,
            $fileSize,
            $originalPath,
            $title,
            $altText,
            $uploadedAt ?? new \DateTimeImmutable()
        );
    }

    public function id(): ImageId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function owner(): OwnerReference
    {
        return $this->owner;
    }

    public function mimeType(): MimeType
    {
        return $this->mimeType;
    }

    public function fileSize(): int
    {
        return $this->fileSize;
    }

    public function originalPath(): FilePath
    {
        return $this->originalPath;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function altText(): ?string
    {
        return $this->altText;
    }

    public function uploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function attachTo(OwnerReference $owner): void
    {
        $this->owner = $owner;
    }

    public function rename(?string $title, ?string $altText): void
    {
        $this->title = $title;
        $this->altText = $altText;
    }

    public function moveOriginal(FilePath $newPath, MimeType $mimeType, int $fileSize): void
    {
        if ($fileSize <= 0) {
            throw new \InvalidArgumentException('File size must be greater than zero.');
        }

        $this->originalPath = $newPath;
        $this->mimeType = $mimeType;
        $this->fileSize = $fileSize;
    }

    public function addOrUpdateThumbnail(Thumbnail $thumbnail): void
    {
        $this->thumbnails[$thumbnail->sizeLabel()->value] = $thumbnail;
    }

    public function removeThumbnail(SizeLabel $sizeLabel): void
    {
        unset($this->thumbnails[$sizeLabel->value]);
    }

    public function getThumbnail(SizeLabel $sizeLabel): ?Thumbnail
    {
        return $this->thumbnails[$sizeLabel->value] ?? null;
    }

    /**
     * @return Thumbnail[]
     */
    public function thumbnails(): array
    {
        return array_values($this->thumbnails);
    }

    public static function reconstitute(
        ImageId $id,
        TenantId $tenantId,
        OwnerReference $owner,
        MimeType $mimeType,
        int $fileSize,
        FilePath $originalPath,
        ?string $title,
        ?string $altText,
        \DateTimeImmutable $uploadedAt,
        array $thumbnails = []
    ): self {
        $image = new self(
            $id,
            $tenantId,
            $owner,
            $mimeType,
            $fileSize,
            $originalPath,
            $title,
            $altText,
            $uploadedAt
        );

        foreach ($thumbnails as $thumbnail) {
            if (!$thumbnail instanceof Thumbnail) {
                throw new \InvalidArgumentException('Invalid thumbnail provided for reconstitution.');
            }

            $image->addOrUpdateThumbnail($thumbnail);
        }

        return $image;
    }

    public function newThumbnail(
        SizeLabel $sizeLabel,
        FilePath $path,
        int $width,
        int $height,
        CropArea $cropArea
    ): Thumbnail {
        return Thumbnail::create(
            ThumbnailId::generate(),
            $this->id,
            $sizeLabel,
            $path,
            $width,
            $height,
            $cropArea
        );
    }
}
