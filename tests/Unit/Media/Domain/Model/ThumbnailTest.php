<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\Model;

use App\Media\Domain\Model\Thumbnail;
use App\Media\Domain\ValueObject\CropArea;
use App\Media\Domain\ValueObject\FilePath;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Domain\ValueObject\SizeLabel;
use App\Media\Domain\ValueObject\ThumbnailId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Thumbnail::class)]
final class ThumbnailTest extends TestCase
{
    private ThumbnailId $thumbnailId;
    private ImageId $imageId;
    private CropArea $cropArea;

    protected function setUp(): void
    {
        $this->thumbnailId = ThumbnailId::fromString('01920000-0000-7000-8000-000000000010');
        $this->imageId = ImageId::fromString('01920000-0000-7000-8000-000000000001');
        $this->cropArea = CropArea::fromDimensions(0, 0, 600, 400);
    }

    // -------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------

    #[Test]
    public function itCreatesWithValidData(): void
    {
        $thumbnail = Thumbnail::create(
            $this->thumbnailId,
            $this->imageId,
            SizeLabel::MEDIUM,
            FilePath::fromString('/thumbnails/image-md.jpg'),
            600,
            400,
            $this->cropArea,
        );

        self::assertTrue($this->thumbnailId->equals($thumbnail->id()));
        self::assertTrue($this->imageId->equals($thumbnail->imageId()));
        self::assertSame(SizeLabel::MEDIUM, $thumbnail->sizeLabel());
        self::assertSame('/thumbnails/image-md.jpg', $thumbnail->path()->toString());
        self::assertSame(600, $thumbnail->width());
        self::assertSame(400, $thumbnail->height());
        self::assertSame($this->cropArea, $thumbnail->cropArea());
    }

    #[Test]
    public function itSetsCreatedAtToNowWhenOmitted(): void
    {
        $before = new \DateTimeImmutable();

        $thumbnail = Thumbnail::create(
            $this->thumbnailId,
            $this->imageId,
            SizeLabel::SMALL,
            FilePath::fromString('/thumbnails/image-sm.jpg'),
            200,
            200,
            CropArea::fromDimensions(0, 0, 200, 200),
        );

        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $thumbnail->createdAt()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $thumbnail->createdAt()->getTimestamp());
    }

    #[Test]
    public function itAcceptsExplicitCreatedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2025-06-15 12:00:00');

        $thumbnail = Thumbnail::create(
            $this->thumbnailId,
            $this->imageId,
            SizeLabel::LARGE,
            FilePath::fromString('/thumbnails/image-lg.jpg'),
            1200,
            800,
            $this->cropArea,
            $createdAt,
        );

        self::assertSame($createdAt->getTimestamp(), $thumbnail->createdAt()->getTimestamp());
    }

    // -------------------------------------------------------------------
    // Rejection
    // -------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenWidthIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Thumbnail width and height must be greater than zero.');

        Thumbnail::create(
            $this->thumbnailId,
            $this->imageId,
            SizeLabel::SMALL,
            FilePath::fromString('/thumbnails/image-sm.jpg'),
            0,
            200,
            $this->cropArea,
        );
    }

    #[Test]
    public function itThrowsWhenHeightIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Thumbnail width and height must be greater than zero.');

        Thumbnail::create(
            $this->thumbnailId,
            $this->imageId,
            SizeLabel::SMALL,
            FilePath::fromString('/thumbnails/image-sm.jpg'),
            200,
            0,
            $this->cropArea,
        );
    }

    #[Test]
    public function itThrowsWhenWidthIsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Thumbnail::create(
            $this->thumbnailId,
            $this->imageId,
            SizeLabel::SMALL,
            FilePath::fromString('/thumbnails/image-sm.jpg'),
            -100,
            200,
            $this->cropArea,
        );
    }

    // -------------------------------------------------------------------
    // State transitions
    // -------------------------------------------------------------------

    #[Test]
    public function itUpdatesPath(): void
    {
        $thumbnail = $this->createMediumThumbnail();
        $newPath = FilePath::fromString('/thumbnails/image-md-v2.jpg');
        $newCrop = CropArea::fromDimensions(10, 10, 580, 380);

        $thumbnail->updatePath($newPath, 580, 380, $newCrop);

        self::assertSame('/thumbnails/image-md-v2.jpg', $thumbnail->path()->toString());
        self::assertSame(580, $thumbnail->width());
        self::assertSame(380, $thumbnail->height());
        self::assertSame($newCrop, $thumbnail->cropArea());
    }

    #[Test]
    public function itThrowsOnUpdatePathWhenWidthIsZero(): void
    {
        $thumbnail = $this->createMediumThumbnail();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Thumbnail width and height must be greater than zero.');

        $thumbnail->updatePath(
            FilePath::fromString('/thumbnails/image-md-v2.jpg'),
            0,
            400,
            $this->cropArea,
        );
    }

    #[Test]
    public function itThrowsOnUpdatePathWhenHeightIsZero(): void
    {
        $thumbnail = $this->createMediumThumbnail();

        $this->expectException(\InvalidArgumentException::class);

        $thumbnail->updatePath(
            FilePath::fromString('/thumbnails/image-md-v2.jpg'),
            600,
            0,
            $this->cropArea,
        );
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private function createMediumThumbnail(): Thumbnail
    {
        return Thumbnail::create(
            $this->thumbnailId,
            $this->imageId,
            SizeLabel::MEDIUM,
            FilePath::fromString('/thumbnails/image-md.jpg'),
            600,
            400,
            $this->cropArea,
        );
    }
}
