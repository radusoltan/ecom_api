<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\Service\DTO;

use App\Media\Domain\Service\DTO\GeneratedThumbnail;
use App\Media\Domain\ValueObject\CropArea;
use App\Media\Domain\ValueObject\FilePath;
use App\Media\Domain\ValueObject\SizeLabel;
use PHPUnit\Framework\TestCase;

final class GeneratedThumbnailTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $path = FilePath::fromString('/uploads/thumb.jpg');
        $sizeLabel = SizeLabel::MEDIUM;
        $cropArea = CropArea::fromDimensions(0, 0, 600, 400);

        $thumbnail = new GeneratedThumbnail($path, $sizeLabel, 600, 400, $cropArea);

        $this->assertTrue($path->equals($thumbnail->path));
        $this->assertSame(SizeLabel::MEDIUM, $thumbnail->sizeLabel);
        $this->assertSame(600, $thumbnail->width);
        $this->assertSame(400, $thumbnail->height);
        $this->assertSame(0, $thumbnail->cropArea->x());
    }

    public function testRejectsZeroWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than zero');

        new GeneratedThumbnail(
            FilePath::fromString('/uploads/thumb.jpg'),
            SizeLabel::SMALL,
            0,
            200,
            CropArea::fromDimensions(0, 0, 200, 200),
        );
    }

    public function testRejectsNegativeHeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than zero');

        new GeneratedThumbnail(
            FilePath::fromString('/uploads/thumb.jpg'),
            SizeLabel::SMALL,
            200,
            -1,
            CropArea::fromDimensions(0, 0, 200, 200),
        );
    }
}
