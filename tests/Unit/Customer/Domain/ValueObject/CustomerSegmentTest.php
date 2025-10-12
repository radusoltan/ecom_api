<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\CustomerSegment;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CustomerSegmentTest extends TestCase
{
    public function testRegularFactoryMethod(): void
    {
        $segment = CustomerSegment::regular();

        self::assertEquals('regular', $segment->toString());
        self::assertTrue($segment->isRegular());
        self::assertFalse($segment->isVip());
        self::assertFalse($segment->isPremium());
    }

    public function testVipFactoryMethod(): void
    {
        $segment = CustomerSegment::vip();

        self::assertEquals('vip', $segment->toString());
        self::assertFalse($segment->isRegular());
        self::assertTrue($segment->isVip());
        self::assertFalse($segment->isPremium());
    }

    public function testPremiumFactoryMethod(): void
    {
        $segment = CustomerSegment::premium();

        self::assertEquals('premium', $segment->toString());
        self::assertFalse($segment->isRegular());
        self::assertFalse($segment->isVip());
        self::assertTrue($segment->isPremium());
    }

    public function testFromStringWithValidSegment(): void
    {
        $regular = CustomerSegment::fromString('regular');
        $vip = CustomerSegment::fromString('vip');
        $premium = CustomerSegment::fromString('premium');

        self::assertTrue($regular->isRegular());
        self::assertTrue($vip->isVip());
        self::assertTrue($premium->isPremium());
    }

    public function testFromStringRejectsInvalidSegment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid customer segment');

        CustomerSegment::fromString('platinum');
    }

    public function testFromStringRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
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
        self::assertEquals('premium', CustomerSegment::premium()->value());
    }

    public function testToStringReturnsSegmentString(): void
    {
        self::assertEquals('regular', CustomerSegment::regular()->toString());
        self::assertEquals('vip', CustomerSegment::vip()->toString());
        self::assertEquals('premium', CustomerSegment::premium()->toString());
    }
}
