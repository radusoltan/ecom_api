<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\CustomerSegment;
use PHPUnit\Framework\TestCase;

final class CustomerSegmentTest extends TestCase
{
    public function testRegularFactoryMethod(): void
    {
        $segment = CustomerSegment::regular();

        self::assertEquals('regular', $segment->toString());
        self::assertTrue($segment->isRegular());
        self::assertFalse($segment->isVip());
        self::assertFalse($segment->isWholesale());
    }

    public function testVipFactoryMethod(): void
    {
        $segment = CustomerSegment::vip();

        self::assertEquals('vip', $segment->toString());
        self::assertFalse($segment->isRegular());
        self::assertTrue($segment->isVip());
        self::assertFalse($segment->isWholesale());
    }

    public function testWholesaleFactoryMethod(): void
    {
        $segment = CustomerSegment::wholesale();

        self::assertEquals('wholesale', $segment->toString());
        self::assertFalse($segment->isRegular());
        self::assertFalse($segment->isVip());
        self::assertTrue($segment->isWholesale());
    }

    public function testFromStringWithValidSegment(): void
    {
        $regular = CustomerSegment::fromString('regular');
        $vip = CustomerSegment::fromString('vip');
        $wholesale = CustomerSegment::fromString('wholesale');

        self::assertTrue($regular->isRegular());
        self::assertTrue($vip->isVip());
        self::assertTrue($wholesale->isWholesale());
    }

    public function testFromStringRejectsInvalidSegment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid customer segment');

        CustomerSegment::fromString('platinum');
    }

    public function testFromStringRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid customer segment');

        CustomerSegment::fromString('');
    }

    public function testEqualsReturnsTrueForSameSegment(): void
    {
        $segment1 = CustomerSegment::vip();
        $segment2 = CustomerSegment::vip();

        self::assertTrue($segment1->equals($segment2));
    }

    public function testEqualsReturnsFalseForDifferentSegments(): void
    {
        $regular = CustomerSegment::regular();
        $vip = CustomerSegment::vip();

        self::assertFalse($regular->equals($vip));
        self::assertFalse($vip->equals($regular));
    }

    public function testValueReturnsSegmentString(): void
    {
        self::assertEquals('regular', CustomerSegment::regular()->value());
        self::assertEquals('vip', CustomerSegment::vip()->value());
        self::assertEquals('wholesale', CustomerSegment::wholesale()->value());
    }

    public function testToStringReturnsSegmentString(): void
    {
        self::assertEquals('regular', CustomerSegment::regular()->toString());
        self::assertEquals('vip', CustomerSegment::vip()->toString());
        self::assertEquals('wholesale', CustomerSegment::wholesale()->toString());
    }

    public function testMagicToStringMethod(): void
    {
        self::assertEquals('regular', (string) CustomerSegment::regular());
        self::assertEquals('vip', (string) CustomerSegment::vip());
        self::assertEquals('wholesale', (string) CustomerSegment::wholesale());
    }

    public function testFromStringHandlesCaseInsensitivity(): void
    {
        $uppercase = CustomerSegment::fromString('VIP');
        $mixedCase = CustomerSegment::fromString('Regular');
        $lowercase = CustomerSegment::fromString('wholesale');

        self::assertTrue($uppercase->isVip());
        self::assertTrue($mixedCase->isRegular());
        self::assertTrue($lowercase->isWholesale());
    }
}
