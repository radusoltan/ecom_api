<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Infrastructure\Persistence\Doctrine\Entity;

use App\Media\Domain\Model\Thumbnail as DomainThumbnail;
use App\Media\Domain\ValueObject\CropArea;
use App\Media\Domain\ValueObject\FilePath;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Domain\ValueObject\SizeLabel;
use App\Media\Domain\ValueObject\ThumbnailId;
use App\Media\Infrastructure\Persistence\Doctrine\Entity\ImageEntity;
use App\Media\Infrastructure\Persistence\Doctrine\Entity\ThumbnailEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ThumbnailEntityTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const THUMBNAIL_UUID = '01900000-0000-7000-8000-000000000010';
    private const IMAGE_UUID = '01900000-0000-7000-8000-000000000020';

    private function makeDomainThumbnail(
        string $sizeLabel = 'md',
        string $path = '/images/thumb.jpg',
        int $width = 320,
        int $height = 240,
        int $cropX = 0,
        int $cropY = 0,
        ?\DateTimeImmutable $createdAt = null,
    ): DomainThumbnail {
        return DomainThumbnail::create(
            ThumbnailId::fromString(self::THUMBNAIL_UUID),
            ImageId::fromString(self::IMAGE_UUID),
            SizeLabel::fromString($sizeLabel),
            FilePath::fromString($path),
            $width,
            $height,
            CropArea::fromDimensions($cropX, $cropY, $width, $height),
            $createdAt ?? new \DateTimeImmutable('2024-03-01T12:00:00+00:00'),
        );
    }

    private function makeImageEntity(): ImageEntity
    {
        $imageEntity = $this->createStub(ImageEntity::class);
        $imageEntity->method('getId')->willReturn(self::IMAGE_UUID);

        return $imageEntity;
    }

    public function testFromDomainModelRoundtrip(): void
    {
        $thumbnail = $this->makeDomainThumbnail();
        $imageEntity = $this->makeImageEntity();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $entity = ThumbnailEntity::fromDomainModel($thumbnail, $imageEntity, $tenantId);

        self::assertSame('md', $entity->getSizeLabel());
    }

    public function testFromDomainDelegatesToFromDomainModel(): void
    {
        $thumbnail = $this->makeDomainThumbnail(sizeLabel: 'lg');
        $imageEntity = $this->makeImageEntity();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $entityViaFromDomain = ThumbnailEntity::fromDomain($thumbnail, $imageEntity, $tenantId);
        $entityViaFromDomainModel = ThumbnailEntity::fromDomainModel($thumbnail, $imageEntity, $tenantId);

        self::assertSame($entityViaFromDomain->getSizeLabel(), $entityViaFromDomainModel->getSizeLabel());
    }

    public function testToDomainModelRestoresAllFields(): void
    {
        $createdAt = new \DateTimeImmutable('2024-03-01T12:00:00+00:00');
        $thumbnail = $this->makeDomainThumbnail(
            sizeLabel: 'sm',
            path: '/uploads/thumb-sm.jpg',
            width: 120,
            height: 90,
            cropX: 5,
            cropY: 10,
            createdAt: $createdAt,
        );
        $imageEntity = $this->makeImageEntity();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $entity = ThumbnailEntity::fromDomainModel($thumbnail, $imageEntity, $tenantId);
        $restored = $entity->toDomainModel();

        self::assertSame(self::THUMBNAIL_UUID, $restored->id()->toString());
        self::assertSame(self::IMAGE_UUID, $restored->imageId()->toString());
        self::assertSame(SizeLabel::SMALL, $restored->sizeLabel());
        self::assertSame('/uploads/thumb-sm.jpg', $restored->path()->toString());
        self::assertSame(120, $restored->width());
        self::assertSame(90, $restored->height());
        self::assertSame(5, $restored->cropArea()->x());
        self::assertSame(10, $restored->cropArea()->y());
        self::assertSame(120, $restored->cropArea()->width());
        self::assertSame(90, $restored->cropArea()->height());
        self::assertSame('2024-03-01T12:00:00+00:00', $restored->createdAt()->format(\DateTimeInterface::ATOM));
    }

    public function testToDomainIsSameAsToDomainModel(): void
    {
        $thumbnail = $this->makeDomainThumbnail();
        $imageEntity = $this->makeImageEntity();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $entity = ThumbnailEntity::fromDomainModel($thumbnail, $imageEntity, $tenantId);

        $viaToDomaim = $entity->toDomain();
        $viaToDomainModel = $entity->toDomainModel();

        self::assertSame($viaToDomaim->id()->toString(), $viaToDomainModel->id()->toString());
    }

    public function testUpdateFromDomainChangesMutableFields(): void
    {
        $original = $this->makeDomainThumbnail(
            sizeLabel: 'md',
            path: '/images/orig.jpg',
            width: 320,
            height: 240,
        );
        $imageEntity = $this->makeImageEntity();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $entity = ThumbnailEntity::fromDomainModel($original, $imageEntity, $tenantId);

        $updated = $this->makeDomainThumbnail(
            sizeLabel: 'xl',
            path: '/images/updated-xl.jpg',
            width: 1280,
            height: 960,
            cropX: 20,
            cropY: 30,
        );
        $entity->updateFromDomain($updated);
        $restored = $entity->toDomainModel();

        self::assertSame('xl', $restored->sizeLabel()->value);
        self::assertSame('/images/updated-xl.jpg', $restored->path()->toString());
        self::assertSame(1280, $restored->width());
        self::assertSame(960, $restored->height());
        self::assertSame(20, $restored->cropArea()->x());
        self::assertSame(30, $restored->cropArea()->y());
    }

    public function testSetImageChangesImageAssociation(): void
    {
        $thumbnail = $this->makeDomainThumbnail();
        $imageEntity = $this->makeImageEntity();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $entity = ThumbnailEntity::fromDomainModel($thumbnail, $imageEntity, $tenantId);

        $newImageUuid = '01900000-0000-7000-8000-000000000099';
        $newImageEntity = $this->createStub(ImageEntity::class);
        $newImageEntity->method('getId')->willReturn($newImageUuid);
        $entity->setImage($newImageEntity);

        $restored = $entity->toDomainModel();
        self::assertSame($newImageUuid, $restored->imageId()->toString());
    }

    public function testGetSizeLabelReturnsString(): void
    {
        $thumbnail = $this->makeDomainThumbnail(sizeLabel: 'xl');
        $imageEntity = $this->makeImageEntity();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $entity = ThumbnailEntity::fromDomainModel($thumbnail, $imageEntity, $tenantId);

        self::assertSame('xl', $entity->getSizeLabel());
    }

    public function testAllSizeLabelsRoundtrip(): void
    {
        $imageEntity = $this->makeImageEntity();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        foreach (['sm', 'md', 'lg', 'xl'] as $sizeLabel) {
            $thumbnail = $this->makeDomainThumbnail(sizeLabel: $sizeLabel);
            $entity = ThumbnailEntity::fromDomainModel($thumbnail, $imageEntity, $tenantId);
            $restored = $entity->toDomainModel();

            self::assertSame($sizeLabel, $restored->sizeLabel()->value);
        }
    }
}
