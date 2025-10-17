<?php

declare(strict_types=1);

namespace App\Media\Domain\Model;

use App\Media\Domain\ValueObject\CropArea;
use App\Media\Domain\ValueObject\FilePath;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Domain\ValueObject\SizeLabel;
use App\Media\Domain\ValueObject\ThumbnailId;

final class Thumbnail
{
    private function __construct(
        private readonly ThumbnailId $id,
        private readonly ImageId $imageId,
        private SizeLabel $sizeLabel,
        private FilePath $path,
        private int $width,
        private int $height,
        private CropArea $cropArea,
        private readonly \DateTimeImmutable $createdAt
    ) {}

    public static function create(
        ThumbnailId $id,
        ImageId $imageId,
        SizeLabel $sizeLabel,
        FilePath $path,
        int $width,
        int $height,
        CropArea $cropArea,
        ?\DateTimeImmutable $createdAt = null
    ): self {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Thumbnail width and height must be greater than zero.');
        }

        return new self(
            $id,
            $imageId,
            $sizeLabel,
            $path,
            $width,
            $height,
            $cropArea,
            $createdAt ?? new \DateTimeImmutable()
        );
    }

    public function id(): ThumbnailId
    {
        return $this->id;
    }

    public function imageId(): ImageId
    {
        return $this->imageId;
    }

    public function sizeLabel(): SizeLabel
    {
        return $this->sizeLabel;
    }

    public function path(): FilePath
    {
        return $this->path;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function cropArea(): CropArea
    {
        return $this->cropArea;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatePath(FilePath $path, int $width, int $height, CropArea $cropArea): void
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Thumbnail width and height must be greater than zero.');
        }

        $this->path = $path;
        $this->width = $width;
        $this->height = $height;
        $this->cropArea = $cropArea;
    }
}
