<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\Service;

use App\Media\Domain\Service\DefaultThumbnailPolicy;
use App\Media\Domain\ValueObject\SizeLabel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultThumbnailPolicy::class)]
final class DefaultThumbnailPolicyExtendedTest extends TestCase
{
    private DefaultThumbnailPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new DefaultThumbnailPolicy();
    }

    // -----------------------------------------------------------------------
    // allowedSizes()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsAllFourAllowedSizes(): void
    {
        $sizes = $this->policy->allowedSizes();

        self::assertCount(4, $sizes);
        self::assertContains(SizeLabel::SMALL, $sizes);
        self::assertContains(SizeLabel::MEDIUM, $sizes);
        self::assertContains(SizeLabel::LARGE, $sizes);
        self::assertContains(SizeLabel::EXTRA_LARGE, $sizes);
    }

    // -----------------------------------------------------------------------
    // dimensionsFor() — happy paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsDimensionsForSmall(): void
    {
        $dims = $this->policy->dimensionsFor(SizeLabel::SMALL);

        self::assertSame(200, $dims['width']);
        self::assertSame(200, $dims['height']);
    }

    #[Test]
    public function itReturnsDimensionsForMedium(): void
    {
        $dims = $this->policy->dimensionsFor(SizeLabel::MEDIUM);

        self::assertSame(600, $dims['width']);
        self::assertSame(400, $dims['height']);
    }

    #[Test]
    public function itReturnsDimensionsForLarge(): void
    {
        $dims = $this->policy->dimensionsFor(SizeLabel::LARGE);

        self::assertSame(1200, $dims['width']);
        self::assertSame(800, $dims['height']);
    }

    #[Test]
    public function itReturnsDimensionsForExtraLarge(): void
    {
        $dims = $this->policy->dimensionsFor(SizeLabel::EXTRA_LARGE);

        self::assertSame(1600, $dims['width']);
        self::assertSame(900, $dims['height']);
    }

    // -----------------------------------------------------------------------
    // assertValid() — happy paths (meets minimum dimension requirement)
    // -----------------------------------------------------------------------

    #[Test]
    public function itAcceptsValidSmallDimensions(): void
    {
        // Min is 50% of 200x200 = 100x100
        $this->policy->assertValid(SizeLabel::SMALL, 200, 200);
        // No exception = pass
        self::assertTrue(true);
    }

    #[Test]
    public function itAcceptsValidMediumDimensions(): void
    {
        // Exact expected dimensions
        $this->policy->assertValid(SizeLabel::MEDIUM, 600, 400);
        self::assertTrue(true);
    }

    #[Test]
    public function itAcceptsValidLargeDimensions(): void
    {
        $this->policy->assertValid(SizeLabel::LARGE, 1200, 800);
        self::assertTrue(true);
    }

    #[Test]
    public function itAcceptsValidExtraLargeDimensions(): void
    {
        $this->policy->assertValid(SizeLabel::EXTRA_LARGE, 1600, 900);
        self::assertTrue(true);
    }

    #[Test]
    public function itAcceptsMinimumAllowedDimensions(): void
    {
        // 50% of 200x200 = 100x100 (minimum for SMALL)
        $this->policy->assertValid(SizeLabel::SMALL, 100, 100);
        self::assertTrue(true);
    }

    // -----------------------------------------------------------------------
    // assertValid() — validation errors
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenWidthIsTooSmall(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('too small');

        // sm minimum = 100x100; providing 50x200 should fail
        $this->policy->assertValid(SizeLabel::SMALL, 50, 200);
    }

    #[Test]
    public function itThrowsWhenHeightIsTooSmall(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('too small');

        // sm minimum = 100x100; providing 200x50 should fail
        $this->policy->assertValid(SizeLabel::SMALL, 200, 50);
    }

    #[Test]
    public function itThrowsWhenDimensionsAreZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be positive integers');

        $this->policy->assertValid(SizeLabel::SMALL, 0, 200);
    }

    #[Test]
    public function itThrowsWhenWidthIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->policy->assertValid(SizeLabel::MEDIUM, 0, 400);
    }

    #[Test]
    public function itThrowsWhenHeightIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->policy->assertValid(SizeLabel::MEDIUM, 600, 0);
    }

    #[Test]
    public function itThrowsForMediumBelowMinimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('too small');

        // md minimum = 300x200 (50% of 600x400); providing 200x150 should fail
        $this->policy->assertValid(SizeLabel::MEDIUM, 200, 150);
    }

    #[Test]
    public function itThrowsForLargeBelowMinimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('too small');

        // lg minimum = 600x400 (50% of 1200x800)
        $this->policy->assertValid(SizeLabel::LARGE, 400, 300);
    }
}
