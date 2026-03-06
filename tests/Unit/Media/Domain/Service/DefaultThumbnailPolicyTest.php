<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\Service;

use App\Media\Domain\Service\DefaultThumbnailPolicy;
use App\Media\Domain\ValueObject\SizeLabel;
use PHPUnit\Framework\TestCase;

final class DefaultThumbnailPolicyTest extends TestCase
{
    private DefaultThumbnailPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new DefaultThumbnailPolicy();
    }

    public function testAllowedSizesReturnsFourSizes(): void
    {
        $sizes = $this->policy->allowedSizes();

        $this->assertCount(4, $sizes);
        $this->assertContains(SizeLabel::SMALL, $sizes);
        $this->assertContains(SizeLabel::MEDIUM, $sizes);
        $this->assertContains(SizeLabel::LARGE, $sizes);
        $this->assertContains(SizeLabel::EXTRA_LARGE, $sizes);
    }

    public function testDimensionsForSmall(): void
    {
        $dims = $this->policy->dimensionsFor(SizeLabel::SMALL);

        $this->assertSame(200, $dims['width']);
        $this->assertSame(200, $dims['height']);
    }

    public function testDimensionsForMedium(): void
    {
        $dims = $this->policy->dimensionsFor(SizeLabel::MEDIUM);

        $this->assertSame(600, $dims['width']);
        $this->assertSame(400, $dims['height']);
    }

    public function testDimensionsForLarge(): void
    {
        $dims = $this->policy->dimensionsFor(SizeLabel::LARGE);

        $this->assertSame(1200, $dims['width']);
        $this->assertSame(800, $dims['height']);
    }

    public function testDimensionsForExtraLarge(): void
    {
        $dims = $this->policy->dimensionsFor(SizeLabel::EXTRA_LARGE);

        $this->assertSame(1600, $dims['width']);
        $this->assertSame(900, $dims['height']);
    }

    public function testAssertValidAcceptsFullSizeDimensions(): void
    {
        $this->policy->assertValid(SizeLabel::SMALL, 200, 200);
        $this->addToAssertionCount(1);
    }

    public function testAssertValidAcceptsLargerThanMinimum(): void
    {
        // 50% of 200x200 = 100x100 minimum
        $this->policy->assertValid(SizeLabel::SMALL, 150, 150);
        $this->addToAssertionCount(1);
    }

    public function testAssertValidRejectsTooSmallDimensions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('too small');

        // 50% of 200 = 100, so 50 is too small
        $this->policy->assertValid(SizeLabel::SMALL, 50, 50);
    }

    public function testAssertValidRejectsZeroWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('positive integers');

        $this->policy->assertValid(SizeLabel::SMALL, 0, 200);
    }

    public function testAssertValidRejectsNegativeHeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('positive integers');

        $this->policy->assertValid(SizeLabel::SMALL, 200, -1);
    }
}
