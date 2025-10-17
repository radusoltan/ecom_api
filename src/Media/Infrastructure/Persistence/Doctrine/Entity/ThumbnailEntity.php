<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Persistence\Doctrine\Entity;

use App\Media\Domain\Model\Thumbnail as DomainThumbnail;
use App\Media\Domain\ValueObject\CropArea;
use App\Media\Domain\ValueObject\FilePath;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Domain\ValueObject\SizeLabel;
use App\Media\Domain\ValueObject\ThumbnailId;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'media_thumbnails')]
class ThumbnailEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\ManyToOne(targetEntity: ImageEntity::class, inversedBy: 'thumbnails')]
    #[ORM\JoinColumn(name: 'image_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ImageEntity $image;

    #[ORM\Column(type: 'string', length: 8, name: 'size_label')]
    private string $sizeLabel;

    #[ORM\Column(type: 'text')]
    private string $path;

    #[ORM\Column(type: 'integer')]
    private int $width;

    #[ORM\Column(type: 'integer')]
    private int $height;

    #[ORM\Column(type: 'json', name: 'crop_json')]
    private array $crop;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public static function fromDomain(DomainThumbnail $thumbnail, ImageEntity $image, TenantId $tenantId): self
    {
        $entity = new self();
        $entity->id = $thumbnail->id()->toString();
        $entity->tenantId = $tenantId->toString();
        $entity->image = $image;
        $entity->sizeLabel = $thumbnail->sizeLabel()->value;
        $entity->path = $thumbnail->path()->toString();
        $entity->width = $thumbnail->width();
        $entity->height = $thumbnail->height();
        $entity->crop = $thumbnail->cropArea()->toArray();
        $entity->createdAt = $thumbnail->createdAt();

        return $entity;
    }

    public function updateFromDomain(DomainThumbnail $thumbnail): void
    {
        $this->sizeLabel = $thumbnail->sizeLabel()->value;
        $this->path = $thumbnail->path()->toString();
        $this->width = $thumbnail->width();
        $this->height = $thumbnail->height();
        $this->crop = $thumbnail->cropArea()->toArray();
    }

    public function toDomain(): DomainThumbnail
    {
        return DomainThumbnail::create(
            ThumbnailId::fromString($this->id),
            ImageId::fromString($this->image->getId()),
            SizeLabel::fromString($this->sizeLabel),
            FilePath::fromString($this->path),
            $this->width,
            $this->height,
            CropArea::fromDimensions(
                (int) ($this->crop['x'] ?? 0),
                (int) ($this->crop['y'] ?? 0),
                (int) ($this->crop['width'] ?? $this->width),
                (int) ($this->crop['height'] ?? $this->height)
            ),
            $this->createdAt
        );
    }

    public function setImage(ImageEntity $image): void
    {
        $this->image = $image;
    }

    public function getSizeLabel(): string
    {
        return $this->sizeLabel;
    }
}
