<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Persistence\Doctrine\Entity;

use App\Media\Domain\Model\Image;
use App\Media\Domain\Model\Thumbnail as DomainThumbnail;
use App\Media\Domain\ValueObject\FilePath;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Domain\ValueObject\MimeType;
use App\Media\Domain\ValueObject\OwnerReference;
use App\Media\Domain\ValueObject\OwnerType;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'media_images')]
class ImageEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private string $id;

    #[ORM\Column(type: 'uuid', name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 16, name: 'owner_type')]
    private string $ownerType;

    #[ORM\Column(type: 'uuid', name: 'owner_id')]
    private string $ownerId;

    #[ORM\Column(type: 'text', name: 'original_path')]
    private string $originalPath;

    #[ORM\Column(type: 'string', length: 128, name: 'mime_type')]
    private string $mimeType;

    #[ORM\Column(type: 'bigint', name: 'file_size')]
    private int $fileSize;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, name: 'alt_text')]
    private ?string $altText = null;

    #[ORM\Column(type: 'datetimetz_immutable', name: 'uploaded_at')]
    private \DateTimeImmutable $uploadedAt;

    #[ORM\Column(type: 'datetimetz_immutable', name: 'deleted_at', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /**
     * @var Collection<int, ThumbnailEntity>
     */
    #[ORM\OneToMany(mappedBy: 'image', targetEntity: ThumbnailEntity::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $thumbnails;

    public function __construct()
    {
        $this->thumbnails = new ArrayCollection();
    }

    public function toDomainModel(): Image
    {
        return $this->toDomain();
    }

    public static function fromDomainModel(Image $image): self
    {
        return self::fromDomain($image);
    }

    public static function fromDomain(Image $image): self
    {
        $entity = new self();
        $entity->id = $image->id()->toString();
        $entity->tenantId = $image->tenantId()->toString();
        $entity->ownerType = $image->owner()->type()->value;
        $entity->ownerId = $image->owner()->ownerId();
        $entity->originalPath = $image->originalPath()->toString();
        $entity->mimeType = $image->mimeType()->toString();
        $entity->fileSize = $image->fileSize();
        $entity->title = $image->title();
        $entity->altText = $image->altText();
        $entity->uploadedAt = $image->uploadedAt();

        $tenantId = $image->tenantId();

        foreach ($image->thumbnails() as $thumbnail) {
            $entity->addThumbnail(ThumbnailEntity::fromDomain($thumbnail, $entity, $tenantId));
        }

        return $entity;
    }

    public function updateFromDomain(Image $image): void
    {
        $this->tenantId = $image->tenantId()->toString();
        $this->ownerType = $image->owner()->type()->value;
        $this->ownerId = $image->owner()->ownerId();
        $this->originalPath = $image->originalPath()->toString();
        $this->mimeType = $image->mimeType()->toString();
        $this->fileSize = $image->fileSize();
        $this->title = $image->title();
        $this->altText = $image->altText();
        $this->uploadedAt = $image->uploadedAt();

        // Synchronize thumbnails
        $tenantId = $image->tenantId();
        $existingBySize = [];
        foreach ($this->thumbnails as $thumbnailEntity) {
            $existingBySize[$thumbnailEntity->getSizeLabel()] = $thumbnailEntity;
        }

        foreach ($image->thumbnails() as $thumbnail) {
            $sizeKey = $thumbnail->sizeLabel()->value;
            if (isset($existingBySize[$sizeKey])) {
                $existingBySize[$sizeKey]->updateFromDomain($thumbnail);
                unset($existingBySize[$sizeKey]);

                continue;
            }

            $this->addThumbnail(ThumbnailEntity::fromDomain($thumbnail, $this, $tenantId));
        }

        // Remove thumbnails no longer present
        foreach ($existingBySize as $toRemove) {
            $this->thumbnails->removeElement($toRemove);
        }
    }

    public function toDomain(): Image
    {
        $thumbnails = array_map(
            static fn (ThumbnailEntity $entity): DomainThumbnail => $entity->toDomain(),
            $this->thumbnails->toArray()
        );

        $tenantId = TenantId::fromString($this->tenantId);

        return Image::reconstitute(
            ImageId::fromString($this->id),
            $tenantId,
            OwnerReference::from(
                $tenantId,
                OwnerType::fromString($this->ownerType),
                $this->ownerId
            ),
            MimeType::fromString($this->mimeType),
            (int) $this->fileSize,
            FilePath::fromString($this->originalPath),
            $this->title,
            $this->altText,
            $this->uploadedAt,
            $thumbnails
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function addThumbnail(ThumbnailEntity $thumbnail): void
    {
        $thumbnail->setImage($this);
        if (!$this->thumbnails->contains($thumbnail)) {
            $this->thumbnails->add($thumbnail);
        }
    }

    /**
     * @return Collection<int, ThumbnailEntity>
     */
    public function getThumbnails(): Collection
    {
        return $this->thumbnails;
    }
}
