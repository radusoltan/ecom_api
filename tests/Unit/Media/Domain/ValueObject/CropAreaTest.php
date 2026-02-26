<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\ValueObject;

use App\Media\Domain\ValueObject\CropArea;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CropArea::class)]
final class CropAreaTest extends TestCase
{
    // -------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------

    #[Test]
    public function itCreatesWithValidDimensions(): void
    {
        $crop = CropArea::fromDimensions(10, 20, 300, 200);

        self::assertSame(10, $crop->x());
        self::assertSame(20, $crop->y());
        self::assertSame(300, $crop->width());
        self::assertSame(200, $crop->height());
    }

    #[Test]
    public function itAllowsZeroOriginCoordinates(): void
    {
        $crop = CropArea::fromDimensions(0, 0, 100, 100);

        self::assertSame(0, $crop->x());
        self::assertSame(0, $crop->y());
    }

    // -------------------------------------------------------------------
    // Rejection
    // -------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenWidthIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Crop area width and height must be greater than zero.');

        CropArea::fromDimensions(0, 0, 0, 100);
    }

    #[Test]
    public function itThrowsWhenHeightIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Crop area width and height must be greater than zero.');

        CropArea::fromDimensions(0, 0, 100, 0);
    }

    #[Test]
    public function itThrowsWhenWidthIsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Crop area width and height must be greater than zero.');

        CropArea::fromDimensions(0, 0, -10, 100);
    }

    #[Test]
    public function itThrowsWhenXIsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Crop area coordinates cannot be negative.');

        CropArea::fromDimensions(-1, 0, 100, 100);
    }

    #[Test]
    public function itThrowsWhenYIsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Crop area coordinates cannot be negative.');

        CropArea::fromDimensions(0, -5, 100, 100);
    }

    // -------------------------------------------------------------------
    // Serialisation
    // -------------------------------------------------------------------

    #[Test]
    public function itConvertsToArray(): void
    {
        $crop = CropArea::fromDimensions(5, 10, 200, 150);

        self::assertSame(
            ['x' => 5, 'y' => 10, 'width' => 200, 'height' => 150],
            $crop->toArray()
        );
    }
}
