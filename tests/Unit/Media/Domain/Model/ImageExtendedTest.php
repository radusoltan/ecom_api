<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\Model;

use App\Media\Domain\Model\Image;
use App\Media\Domain\Model\Thumbnail;
use App\Media\Domain\ValueObject\CropArea;
use App\Media\Domain\ValueObject\FilePath;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Domain\ValueObject\MimeType;
use App\Media\Domain\ValueObject\OwnerReference;
use App\Media\Domain\ValueObject\OwnerType;
use App\Media\Domain\ValueObject\SizeLabel;
use App\Media\Domain\ValueObject\ThumbnailId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Image::class)]
final class ImageExtendedTest extends TestCase
{
    private TenantId $tenantId;
    private OwnerReference $owner;
    private MimeType $mimeType;
    private FilePath $originalPath;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->owner = OwnerReference::from($this->tenantId, OwnerType::PRODUCT, 'prod-001');
        $this->mimeType = MimeType::fromString('image/jpeg');
        $this->originalPath = FilePath::fromString('/uploads/images/test.jpg');
    }

    private function createImage(
        int $fileSize = 102400,
        ?string $title = null,
        ?string $altText = null,
    ): Image {
        return Image::upload(
            tenantId: $this->tenantId,
            owner: $this->owner,
            mimeType: $this->mimeType,
            fileSize: $fileSize,
            originalPath: $this->originalPath,
            title: $title,
            altText: $altText,
        );
    }

    // -----------------------------------------------------------------------
    // Image::upload()
    // -----------------------------------------------------------------------

    #[Test]
    public function itUploadsImageWithAllRequiredFields(): void
    {
        $image = $this->createImage(fileSize: 204800, title: 'Test Image', altText: 'Alt text');

        self::assertInstanceOf(ImageId::class, $image->id());
        self::assertTrue($this->tenantId->equals($image->tenantId()));
        self::assertSame(204800, $image->fileSize());
        self::assertSame('Test Image', $image->title());
        self::assertSame('Alt text', $image->altText());
        self::assertInstanceOf(\DateTimeImmutable::class, $image->uploadedAt());
    }

    #[Test]
    public function itUploadsImageWithNullTitleAndAltText(): void
    {
        $image = $this->createImage();

        self::assertNull($image->title());
        self::assertNull($image->altText());
    }

    #[Test]
    public function itUsesProvidedIdWhenGiven(): void
    {
        $id = ImageId::generate();

        $image = Image::upload(
            tenantId: $this->tenantId,
            owner: $this->owner,
            mimeType: $this->mimeType,
            fileSize: 1024,
            originalPath: $this->originalPath,
            id: $id,
        );

        self::assertTrue($id->equals($image->id()));
    }

    #[Test]
    public function itUsesProvidedUploadedAt(): void
    {
        $uploadedAt = new \DateTimeImmutable('2024-01-15 10:00:00');

        $image = Image::upload(
            tenantId: $this->tenantId,
            owner: $this->owner,
            mimeType: $this->mimeType,
            fileSize: 1024,
            originalPath: $this->originalPath,
            uploadedAt: $uploadedAt,
        );

        self::assertSame($uploadedAt->getTimestamp(), $image->uploadedAt()->getTimestamp());
    }

    #[Test]
    public function itThrowsWhenFileSizeIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File size must be greater than zero');

        Image::upload(
            tenantId: $this->tenantId,
            owner: $this->owner,
            mimeType: $this->mimeType,
            fileSize: 0,
            originalPath: $this->originalPath,
        );
    }

    #[Test]
    public function itThrowsWhenFileSizeIsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Image::upload(
            tenantId: $this->tenantId,
            owner: $this->owner,
            mimeType: $this->mimeType,
            fileSize: -1,
            originalPath: $this->originalPath,
        );
    }

    // -----------------------------------------------------------------------
    // Image::reconstitute()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReconstituteImageFromPersistence(): void
    {
        $id = ImageId::generate();
        $uploadedAt = new \DateTimeImmutable('2024-01-01');

        $image = Image::reconstitute(
            id: $id,
            tenantId: $this->tenantId,
            owner: $this->owner,
            mimeType: $this->mimeType,
            fileSize: 512,
            originalPath: $this->originalPath,
            title: 'Reconstituted',
            altText: 'Alt',
            uploadedAt: $uploadedAt,
        );

        self::assertTrue($id->equals($image->id()));
        self::assertSame('Reconstituted', $image->title());
        self::assertSame(512, $image->fileSize());
        self::assertSame($uploadedAt->getTimestamp(), $image->uploadedAt()->getTimestamp());
    }

    #[Test]
    public function itReconstitutesImageWithThumbnails(): void
    {
        $id = ImageId::generate();
        $cropArea = CropArea::fromDimensions(0, 0, 200, 200);
        $thumbnail = Thumbnail::create(
            ThumbnailId::generate(),
            $id,
            SizeLabel::SMALL,
            FilePath::fromString('/thumbs/sm.jpg'),
            200,
            200,
            $cropArea,
        );

        $image = Image::reconstitute(
            id: $id,
            tenantId: $this->tenantId,
            owner: $this->owner,
            mimeType: $this->mimeType,
            fileSize: 1024,
            originalPath: $this->originalPath,
            title: null,
            altText: null,
            uploadedAt: new \DateTimeImmutable(),
            thumbnails: [$thumbnail],
        );

        self::assertCount(1, $image->thumbnails());
    }

    #[Test]
    public function itThrowsWhenInvalidThumbnailPassedToReconstitute(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid thumbnail');

        Image::reconstitute(
            id: ImageId::generate(),
            tenantId: $this->tenantId,
            owner: $this->owner,
            mimeType: $this->mimeType,
            fileSize: 1024,
            originalPath: $this->originalPath,
            title: null,
            altText: null,
            uploadedAt: new \DateTimeImmutable(),
            thumbnails: ['not-a-thumbnail'],
        );
    }

    // -----------------------------------------------------------------------
    // attachTo()
    // -----------------------------------------------------------------------

    #[Test]
    public function itAttachesImageToNewOwner(): void
    {
        $image = $this->createImage();
        $newOwner = OwnerReference::from($this->tenantId, OwnerType::CATEGORY, 'cat-001');

        $image->attachTo($newOwner);

        self::assertSame('cat-001', $image->owner()->ownerId());
        self::assertSame(OwnerType::CATEGORY, $image->owner()->type());
    }

    // -----------------------------------------------------------------------
    // rename()
    // -----------------------------------------------------------------------

    #[Test]
    public function itRenamesImageWithNewTitleAndAltText(): void
    {
        $image = $this->createImage(title: 'Old Title', altText: 'Old Alt');

        $image->rename('New Title', 'New Alt');

        self::assertSame('New Title', $image->title());
        self::assertSame('New Alt', $image->altText());
    }

    #[Test]
    public function itRenamesImageToNull(): void
    {
        $image = $this->createImage(title: 'Title', altText: 'Alt');

        $image->rename(null, null);

        self::assertNull($image->title());
        self::assertNull($image->altText());
    }

    // -----------------------------------------------------------------------
    // moveOriginal()
    // -----------------------------------------------------------------------

    #[Test]
    public function itMovesOriginalToNewPath(): void
    {
        $image = $this->createImage();
        $newPath = FilePath::fromString('/uploads/processed/test.webp');
        $newMime = MimeType::fromString('image/webp');

        $image->moveOriginal($newPath, $newMime, 51200);

        self::assertTrue($newPath->equals($image->originalPath()));
        self::assertTrue($newMime->equals($image->mimeType()));
        self::assertSame(51200, $image->fileSize());
    }

    #[Test]
    public function itThrowsWhenMoveOriginalWithZeroFileSize(): void
    {
        $image = $this->createImage();
        $newPath = FilePath::fromString('/uploads/new.jpg');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File size must be greater than zero');

        $image->moveOriginal($newPath, $this->mimeType, 0);
    }

    // -----------------------------------------------------------------------
    // Thumbnail management
    // -----------------------------------------------------------------------

    #[Test]
    public function itAddsThumbnailAndCanRetrieveIt(): void
    {
        $image = $this->createImage();
        $cropArea = CropArea::fromDimensions(0, 0, 200, 200);
        $thumbnail = Thumbnail::create(
            ThumbnailId::generate(),
            $image->id(),
            SizeLabel::SMALL,
            FilePath::fromString('/thumbs/sm.jpg'),
            200,
            200,
            $cropArea,
        );

        $image->addOrUpdateThumbnail($thumbnail);

        $retrieved = $image->getThumbnail(SizeLabel::SMALL);
        self::assertNotNull($retrieved);
        self::assertSame(SizeLabel::SMALL, $retrieved->sizeLabel());
    }

    #[Test]
    public function itUpdatesExistingThumbnailForSameSize(): void
    {
        $image = $this->createImage();
        $cropArea = CropArea::fromDimensions(0, 0, 200, 200);

        $thumb1 = Thumbnail::create(
            ThumbnailId::generate(),
            $image->id(),
            SizeLabel::SMALL,
            FilePath::fromString('/thumbs/sm-v1.jpg'),
            200, 200, $cropArea,
        );
        $thumb2 = Thumbnail::create(
            ThumbnailId::generate(),
            $image->id(),
            SizeLabel::SMALL,
            FilePath::fromString('/thumbs/sm-v2.jpg'),
            200, 200, $cropArea,
        );

        $image->addOrUpdateThumbnail($thumb1);
        $image->addOrUpdateThumbnail($thumb2);

        self::assertCount(1, $image->thumbnails());
    }

    #[Test]
    public function itRemovesThumbnail(): void
    {
        $image = $this->createImage();
        $cropArea = CropArea::fromDimensions(0, 0, 200, 200);
        $thumbnail = Thumbnail::create(
            ThumbnailId::generate(),
            $image->id(),
            SizeLabel::MEDIUM,
            FilePath::fromString('/thumbs/md.jpg'),
            600, 400, $cropArea,
        );

        $image->addOrUpdateThumbnail($thumbnail);
        $image->removeThumbnail(SizeLabel::MEDIUM);

        self::assertNull($image->getThumbnail(SizeLabel::MEDIUM));
        self::assertCount(0, $image->thumbnails());
    }

    #[Test]
    public function itReturnsNullForNonExistentThumbnail(): void
    {
        $image = $this->createImage();

        self::assertNull($image->getThumbnail(SizeLabel::LARGE));
    }

    #[Test]
    public function itReturnsThumbnailsAsArray(): void
    {
        $image = $this->createImage();
        $cropArea = CropArea::fromDimensions(0, 0, 200, 200);

        foreach ([SizeLabel::SMALL, SizeLabel::MEDIUM, SizeLabel::LARGE] as $size) {
            $thumb = Thumbnail::create(
                ThumbnailId::generate(),
                $image->id(),
                $size,
                FilePath::fromString("/thumbs/{$size->value}.jpg"),
                200, 200, $cropArea,
            );
            $image->addOrUpdateThumbnail($thumb);
        }

        $thumbnails = $image->thumbnails();
        self::assertCount(3, $thumbnails);
        self::assertContainsOnlyInstancesOf(Thumbnail::class, $thumbnails);
    }

    // -----------------------------------------------------------------------
    // newThumbnail()
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesNewThumbnailWithImageId(): void
    {
        $image = $this->createImage();
        $cropArea = CropArea::fromDimensions(0, 0, 600, 400);

        $thumbnail = $image->newThumbnail(
            SizeLabel::MEDIUM,
            FilePath::fromString('/thumbs/md.jpg'),
            600, 400,
            $cropArea,
        );

        self::assertSame(SizeLabel::MEDIUM, $thumbnail->sizeLabel());
        self::assertTrue($image->id()->equals($thumbnail->imageId()));
        self::assertSame(600, $thumbnail->width());
        self::assertSame(400, $thumbnail->height());
    }
}
