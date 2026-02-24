<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\Model;

use App\Media\Domain\Model\Image;
use App\Media\Domain\ValueObject\CropArea;
use App\Media\Domain\ValueObject\FilePath;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Domain\ValueObject\MimeType;
use App\Media\Domain\ValueObject\OwnerReference;
use App\Media\Domain\ValueObject\OwnerType;
use App\Media\Domain\ValueObject\SizeLabel;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Image::class)]
final class ImageTest extends TestCase
{
    private TenantId $tenantId;
    private OwnerReference $owner;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->owner = OwnerReference::from(
            $this->tenantId,
            OwnerType::fromString('product'),
            'prod-123',
        );
    }

    #[Test]
    public function uploadCreatesImage(): void
    {
        $image = Image::upload(
            $this->tenantId,
            $this->owner,
            MimeType::fromString('image/jpeg'),
            1024,
            FilePath::fromString('/images/test.jpg'),
            'Test Image',
            'A test image',
        );

        self::assertSame('image/jpeg', $image->mimeType()->toString());
        self::assertSame(1024, $image->fileSize());
        self::assertSame('/images/test.jpg', $image->originalPath()->toString());
        self::assertSame('Test Image', $image->title());
        self::assertSame('A test image', $image->altText());
        self::assertSame($this->tenantId, $image->tenantId());
    }

    #[Test]
    public function uploadRejectsZeroFileSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File size must be greater than zero');

        Image::upload(
            $this->tenantId,
            $this->owner,
            MimeType::fromString('image/png'),
            0,
            FilePath::fromString('/images/test.png'),
        );
    }

    #[Test]
    public function renameUpdatesMetadata(): void
    {
        $image = $this->createImage();

        $image->rename('New Title', 'New Alt');

        self::assertSame('New Title', $image->title());
        self::assertSame('New Alt', $image->altText());
    }

    #[Test]
    public function attachToChangesOwner(): void
    {
        $image = $this->createImage();
        $newOwner = OwnerReference::from(
            $this->tenantId,
            OwnerType::fromString('category'),
            'cat-456',
        );

        $image->attachTo($newOwner);

        self::assertSame($newOwner, $image->owner());
    }

    #[Test]
    public function thumbnailManagement(): void
    {
        $image = $this->createImage();
        $sizeLabel = SizeLabel::from('md');

        self::assertCount(0, $image->thumbnails());
        self::assertNull($image->getThumbnail($sizeLabel));

        $thumbnail = $image->newThumbnail(
            $sizeLabel,
            FilePath::fromString('/thumbnails/test-md.jpg'),
            200,
            200,
            CropArea::fromDimensions(0, 0, 200, 200),
        );
        $image->addOrUpdateThumbnail($thumbnail);

        self::assertCount(1, $image->thumbnails());
        self::assertNotNull($image->getThumbnail($sizeLabel));

        $image->removeThumbnail($sizeLabel);

        self::assertCount(0, $image->thumbnails());
    }

    #[Test]
    public function reconstituteRestoresImage(): void
    {
        $imageId = ImageId::generate();
        $image = Image::reconstitute(
            $imageId,
            $this->tenantId,
            $this->owner,
            MimeType::fromString('image/webp'),
            2048,
            FilePath::fromString('/images/stored.webp'),
            'Stored',
            'Alt text',
            new \DateTimeImmutable('2026-01-01'),
        );

        self::assertTrue($image->id()->equals($imageId));
        self::assertSame('Stored', $image->title());
        self::assertSame(2048, $image->fileSize());
    }

    private function createImage(): Image
    {
        return Image::upload(
            $this->tenantId,
            $this->owner,
            MimeType::fromString('image/jpeg'),
            1024,
            FilePath::fromString('/images/test.jpg'),
            'Original Title',
            'Original Alt',
        );
    }
}
