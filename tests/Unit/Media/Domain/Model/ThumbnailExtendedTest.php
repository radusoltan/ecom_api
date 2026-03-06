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
final class ThumbnailExtendedTest extends TestCase
{
    private ThumbnailId $id;
    private ImageId $imageId;
    private SizeLabel $sizeLabel;
    private FilePath $path;
    private CropArea $cropArea;

    protected function setUp(): void
    {
        $this->id = ThumbnailId::generate();
        $this->imageId = ImageId::generate();
        $this->sizeLabel = SizeLabel::MEDIUM;
        $this->path = FilePath::fromString('/thumbs/md.jpg');
        $this->cropArea = CropArea::fromDimensions(0, 0, 600, 400);
    }

    private function createThumbnail(
        int $width = 600,
        int $height = 400,
    ): Thumbnail {
        return Thumbnail::create(
            $this->id,
            $this->imageId,
            $this->sizeLabel,
            $this->path,
            $width,
            $height,
            $this->cropArea,
        );
    }

    // -----------------------------------------------------------------------
    // Thumbnail::create()
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesWithAllRequiredFields(): void
    {
        $thumb = $this->createThumbnail();

        self::assertTrue($this->id->equals($thumb->id()));
        self::assertTrue($this->imageId->equals($thumb->imageId()));
        self::assertSame(SizeLabel::MEDIUM, $thumb->sizeLabel());
        self::assertTrue($this->path->equals($thumb->path()));
        self::assertSame(600, $thumb->width());
        self::assertSame(400, $thumb->height());
        self::assertInstanceOf(\DateTimeImmutable::class, $thumb->createdAt());
    }

    #[Test]
    public function itUsesProvidedCreatedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-01 12:00:00');

        $thumb = Thumbnail::create(
            $this->id, $this->imageId, $this->sizeLabel, $this->path,
            600, 400, $this->cropArea, $createdAt,
        );

        self::assertSame($createdAt->getTimestamp(), $thumb->createdAt()->getTimestamp());
    }

    #[Test]
    public function itUsesCurrentTimeWhenCreatedAtNotProvided(): void
    {
        $before = new \DateTimeImmutable();
        $thumb = $this->createThumbnail();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $thumb->createdAt()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $thumb->createdAt()->getTimestamp());
    }

    #[Test]
    public function itThrowsWhenWidthIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('width and height must be greater than zero');

        $this->createThumbnail(width: 0);
    }

    #[Test]
    public function itThrowsWhenHeightIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('width and height must be greater than zero');

        $this->createThumbnail(height: 0);
    }

    #[Test]
    public function itThrowsWhenWidthIsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createThumbnail(width: -100);
    }

    #[Test]
    public function itThrowsWhenHeightIsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createThumbnail(height: -100);
    }

    // -----------------------------------------------------------------------
    // Create with each size label
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesSmallThumbnail(): void
    {
        $thumb = Thumbnail::create(
            $this->id, $this->imageId, SizeLabel::SMALL, $this->path,
            200, 200, CropArea::fromDimensions(0, 0, 200, 200),
        );

        self::assertSame(SizeLabel::SMALL, $thumb->sizeLabel());
    }

    #[Test]
    public function itCreatesLargeThumbnail(): void
    {
        $thumb = Thumbnail::create(
            $this->id, $this->imageId, SizeLabel::LARGE, $this->path,
            1200, 800, CropArea::fromDimensions(0, 0, 1200, 800),
        );

        self::assertSame(SizeLabel::LARGE, $thumb->sizeLabel());
    }

    #[Test]
    public function itCreatesExtraLargeThumbnail(): void
    {
        $thumb = Thumbnail::create(
            $this->id, $this->imageId, SizeLabel::EXTRA_LARGE, $this->path,
            1600, 900, CropArea::fromDimensions(0, 0, 1600, 900),
        );

        self::assertSame(SizeLabel::EXTRA_LARGE, $thumb->sizeLabel());
    }

    // -----------------------------------------------------------------------
    // updatePath()
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesPathWidthAndHeight(): void
    {
        $thumb = $this->createThumbnail();
        $newPath = FilePath::fromString('/thumbs/updated-md.jpg');
        $newCropArea = CropArea::fromDimensions(10, 10, 580, 380);

        $thumb->updatePath($newPath, 580, 380, $newCropArea);

        self::assertTrue($newPath->equals($thumb->path()));
        self::assertSame(580, $thumb->width());
        self::assertSame(380, $thumb->height());
    }

    #[Test]
    public function itThrowsWhenUpdatePathWithZeroWidth(): void
    {
        $thumb = $this->createThumbnail();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('width and height must be greater than zero');

        $thumb->updatePath($this->path, 0, 400, $this->cropArea);
    }

    #[Test]
    public function itThrowsWhenUpdatePathWithZeroHeight(): void
    {
        $thumb = $this->createThumbnail();

        $this->expectException(\InvalidArgumentException::class);

        $thumb->updatePath($this->path, 600, 0, $this->cropArea);
    }

    #[Test]
    public function itThrowsWhenUpdatePathWithNegativeDimensions(): void
    {
        $thumb = $this->createThumbnail();

        $this->expectException(\InvalidArgumentException::class);

        $thumb->updatePath($this->path, -1, 400, $this->cropArea);
    }

    // -----------------------------------------------------------------------
    // cropArea()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsCropArea(): void
    {
        $cropArea = CropArea::fromDimensions(10, 20, 580, 380);
        $thumb = Thumbnail::create(
            $this->id, $this->imageId, $this->sizeLabel, $this->path,
            580, 380, $cropArea,
        );

        $retrieved = $thumb->cropArea();
        self::assertSame(10, $retrieved->x());
        self::assertSame(20, $retrieved->y());
        self::assertSame(580, $retrieved->width());
        self::assertSame(380, $retrieved->height());
    }
}
