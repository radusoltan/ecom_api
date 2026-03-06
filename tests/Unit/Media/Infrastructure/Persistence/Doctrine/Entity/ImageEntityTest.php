<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Infrastructure\Persistence\Doctrine\Entity;

use App\Media\Domain\Model\Image;
use App\Media\Domain\ValueObject\FilePath;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Domain\ValueObject\MimeType;
use App\Media\Domain\ValueObject\OwnerReference;
use App\Media\Domain\ValueObject\OwnerType;
use App\Media\Infrastructure\Persistence\Doctrine\Entity\ImageEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ImageEntityTest extends TestCase
{
    private TenantId $tenantId;
    private OwnerReference $owner;
    private ImageId $imageId;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->owner = OwnerReference::from(
            $this->tenantId,
            OwnerType::fromString('product'),
            '00000000-0000-4000-8000-000000000010'
        );
        $this->imageId = ImageId::generate();
    }

    public function testFromDomainModelRoundtrip(): void
    {
        $uploadedAt = new \DateTimeImmutable('2026-01-10 09:00:00');

        $domain = Image::upload(
            $this->tenantId,
            $this->owner,
            MimeType::fromString('image/jpeg'),
            2048,
            FilePath::fromString('/uploads/products/photo.jpg'),
            'Product Photo',
            'A product photo',
            $uploadedAt,
            $this->imageId
        );

        $entity = ImageEntity::fromDomainModel($domain);

        self::assertSame($this->imageId->toString(), $entity->getId());
        self::assertSame($this->tenantId->toString(), $entity->getTenantId());
        self::assertCount(0, $entity->getThumbnails());

        $restored = $entity->toDomainModel();
        self::assertSame($this->imageId->toString(), $restored->id()->toString());
        self::assertSame($this->tenantId->toString(), $restored->tenantId()->toString());
        self::assertSame('image/jpeg', $restored->mimeType()->toString());
        self::assertSame(2048, $restored->fileSize());
        self::assertSame('/uploads/products/photo.jpg', $restored->originalPath()->toString());
        self::assertSame('Product Photo', $restored->title());
        self::assertSame('A product photo', $restored->altText());
        self::assertSame($uploadedAt->getTimestamp(), $restored->uploadedAt()->getTimestamp());
    }

    public function testFromDomainModelWithNullTitleAndAltText(): void
    {
        $domain = Image::upload(
            $this->tenantId,
            $this->owner,
            MimeType::fromString('image/png'),
            1024,
            FilePath::fromString('/uploads/images/raw.png'),
            null,
            null,
            null,
            $this->imageId
        );

        $entity = ImageEntity::fromDomainModel($domain);

        $restored = $entity->toDomainModel();
        self::assertNull($restored->title());
        self::assertNull($restored->altText());
    }

    public function testFromDomainPreservesOwnerReference(): void
    {
        $domain = Image::upload(
            $this->tenantId,
            $this->owner,
            MimeType::fromString('image/webp'),
            512,
            FilePath::fromString('/uploads/cat/image.webp'),
            null,
            null,
            null,
            $this->imageId
        );

        $entity = ImageEntity::fromDomainModel($domain);
        $restored = $entity->toDomainModel();

        self::assertSame('product', $restored->owner()->type()->value);
        self::assertSame('00000000-0000-4000-8000-000000000010', $restored->owner()->ownerId());
    }

    public function testFromDomainModelWithCategoryOwner(): void
    {
        $categoryOwner = OwnerReference::from(
            $this->tenantId,
            OwnerType::fromString('category'),
            '00000000-0000-4000-8000-000000000020'
        );

        $domain = Image::upload(
            $this->tenantId,
            $categoryOwner,
            MimeType::fromString('image/jpeg'),
            4096,
            FilePath::fromString('/uploads/categories/banner.jpg'),
            'Category Banner',
            null,
            null,
            $this->imageId
        );

        $entity = ImageEntity::fromDomainModel($domain);
        $restored = $entity->toDomainModel();

        self::assertSame('category', $restored->owner()->type()->value);
        self::assertSame('00000000-0000-4000-8000-000000000020', $restored->owner()->ownerId());
        self::assertSame('Category Banner', $restored->title());
    }

    public function testSetId(): void
    {
        $entity = new ImageEntity();
        $entity->setId('new-id-value');

        self::assertSame('new-id-value', $entity->getId());
    }

    public function testFromDomainRoundtripPreservesFileSize(): void
    {
        $domain = Image::upload(
            $this->tenantId,
            $this->owner,
            MimeType::fromString('image/gif'),
            99999,
            FilePath::fromString('/uploads/anim.gif'),
            null,
            null,
            null,
            $this->imageId
        );

        $entity = ImageEntity::fromDomainModel($domain);
        $restored = $entity->toDomainModel();

        self::assertSame(99999, $restored->fileSize());
        self::assertSame('image/gif', $restored->mimeType()->toString());
    }

    public function testThumbnailsCollectionIsEmptyByDefault(): void
    {
        $domain = Image::upload(
            $this->tenantId,
            $this->owner,
            MimeType::fromString('image/jpeg'),
            1500,
            FilePath::fromString('/uploads/test.jpg'),
            null,
            null,
            null,
            $this->imageId
        );

        $entity = ImageEntity::fromDomainModel($domain);

        self::assertCount(0, $entity->getThumbnails());
    }
}
