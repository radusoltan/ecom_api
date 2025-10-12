<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\ValueObject;

use App\Pricing\Domain\ValueObject\CouponCode;
use PHPUnit\Framework\TestCase;

final class CouponCodeTest extends TestCase
{
    public function testFromStringCreatesValidCouponCode(): void
    {
        $code = CouponCode::fromString('SAVE20');

        $this->assertSame('SAVE20', $code->toString());
    }

    public function testFromStringConvertsToUppercase(): void
    {
        $code = CouponCode::fromString('save20');

        $this->assertSame('SAVE20', $code->toString());
    }

    public function testFromStringTrimWhitespace(): void
    {
        $code = CouponCode::fromString('  SAVE20  ');

        $this->assertSame('SAVE20', $code->toString());
    }

    public function testFromStringThrowsExceptionForTooShortCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CouponCode must be between 4 and 20 characters');

        CouponCode::fromString('ABC');
    }

    public function testFromStringThrowsExceptionForTooLongCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CouponCode must be between 4 and 20 characters');

        CouponCode::fromString('ABCDEFGHIJKLMNOPQRSTUVWXYZ');
    }

    public function testFromStringThrowsExceptionForNonAlphanumeric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CouponCode must contain only uppercase alphanumeric');

        CouponCode::fromString('SAVE-20');
    }

    public function testFromStringAcceptsNumericCharacters(): void
    {
        $code = CouponCode::fromString('SAVE2024');

        $this->assertSame('SAVE2024', $code->toString());
    }

    public function testEqualsReturnsTrueForSameCode(): void
    {
        $code1 = CouponCode::fromString('SAVE20');
        $code2 = CouponCode::fromString('SAVE20');

        $this->assertTrue($code1->equals($code2));
    }

    public function testEqualsReturnsFalseForDifferentCodes(): void
    {
        $code1 = CouponCode::fromString('SAVE20');
        $code2 = CouponCode::fromString('SAVE30');

        $this->assertFalse($code1->equals($code2));
    }

    public function testMinimumLengthAccepted(): void
    {
        $code = CouponCode::fromString('ABCD');

        $this->assertSame('ABCD', $code->toString());
    }

    public function testMaximumLengthAccepted(): void
    {
        $code = CouponCode::fromString('ABCDEFGHIJ1234567890');

        $this->assertSame('ABCDEFGHIJ1234567890', $code->toString());
    }
}
