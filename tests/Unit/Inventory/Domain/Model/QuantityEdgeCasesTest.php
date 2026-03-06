<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\Model;

use App\Inventory\Domain\Model\Quantity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Quantity::class)]
final class QuantityEdgeCasesTest extends TestCase
{
    // ---------------------------------------------------------------
    // Boundary creation
    // ---------------------------------------------------------------

    #[Test]
    public function itAcceptsZeroValue(): void
    {
        $qty = Quantity::fromInt(0);

        self::assertSame(0, $qty->value());
        self::assertTrue($qty->isZero());
    }

    #[Test]
    public function itAcceptsMaxValue(): void
    {
        $qty = Quantity::fromInt(999999);

        self::assertSame(999999, $qty->value());
        self::assertFalse($qty->isZero());
        self::assertTrue($qty->isPositive());
    }

    #[Test]
    public function itThrowsForMinusOne(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Quantity cannot be negative: -1');

        Quantity::fromInt(-1);
    }

    #[Test]
    public function itThrowsForLargeNegativeValue(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Quantity cannot be negative');

        Quantity::fromInt(-999999);
    }

    #[Test]
    public function itThrowsForExactlyOneOverMax(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Quantity cannot exceed 999999: 1000000');

        Quantity::fromInt(1000000);
    }

    // ---------------------------------------------------------------
    // zero() factory
    // ---------------------------------------------------------------

    #[Test]
    public function itZeroIsIdenticalToFromIntZero(): void
    {
        $a = Quantity::zero();
        $b = Quantity::fromInt(0);

        self::assertTrue($a->equals($b));
    }

    // ---------------------------------------------------------------
    // add / subtract immutability
    // ---------------------------------------------------------------

    #[Test]
    public function itAddReturnsNewInstanceLeavingOriginalUnchanged(): void
    {
        $a = Quantity::fromInt(10);
        $b = Quantity::fromInt(5);

        $result = $a->add($b);

        self::assertSame(10, $a->value()); // unchanged
        self::assertSame(5, $b->value());  // unchanged
        self::assertSame(15, $result->value());
    }

    #[Test]
    public function itSubtractReturnsNewInstanceLeavingOriginalUnchanged(): void
    {
        $a = Quantity::fromInt(10);
        $b = Quantity::fromInt(3);

        $result = $a->subtract($b);

        self::assertSame(10, $a->value()); // unchanged
        self::assertSame(7, $result->value());
    }

    #[Test]
    public function itSubtractResultingInZeroIsStillValid(): void
    {
        $a = Quantity::fromInt(5);
        $b = Quantity::fromInt(5);

        $result = $a->subtract($b);

        self::assertTrue($result->isZero());
    }

    #[Test]
    public function itAddingToMaxProducesException(): void
    {
        $max = Quantity::fromInt(999999);
        $one = Quantity::fromInt(1);

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Quantity cannot exceed 999999');

        $max->add($one);
    }

    #[Test]
    public function itSubtractingMoreThanSelfThrows(): void
    {
        $a = Quantity::fromInt(5);
        $b = Quantity::fromInt(6);

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Quantity cannot be negative');

        $a->subtract($b);
    }

    // ---------------------------------------------------------------
    // Comparison methods
    // ---------------------------------------------------------------

    #[Test]
    public function itIsGreaterThanWithSmallerValue(): void
    {
        $big = Quantity::fromInt(10);
        $small = Quantity::fromInt(3);

        self::assertTrue($big->isGreaterThan($small));
        self::assertFalse($small->isGreaterThan($big));
    }

    #[Test]
    public function itIsGreaterThanReturnsFalseForEqualValues(): void
    {
        $a = Quantity::fromInt(7);
        $b = Quantity::fromInt(7);

        self::assertFalse($a->isGreaterThan($b));
    }

    #[Test]
    public function itIsGreaterThanOrEqualForEqual(): void
    {
        $a = Quantity::fromInt(7);
        $b = Quantity::fromInt(7);

        self::assertTrue($a->isGreaterThanOrEqual($b));
    }

    #[Test]
    public function itIsLessThanForSmallerValue(): void
    {
        $a = Quantity::fromInt(3);
        $b = Quantity::fromInt(5);

        self::assertTrue($a->isLessThan($b));
        self::assertFalse($b->isLessThan($a));
    }

    #[Test]
    public function itIsLessThanReturnsFalseForEqualValues(): void
    {
        $a = Quantity::fromInt(4);
        $b = Quantity::fromInt(4);

        self::assertFalse($a->isLessThan($b));
    }

    #[Test]
    public function itIsLessThanOrEqualForEqual(): void
    {
        $a = Quantity::fromInt(6);
        $b = Quantity::fromInt(6);

        self::assertTrue($a->isLessThanOrEqual($b));
    }

    // ---------------------------------------------------------------
    // isPositive
    // ---------------------------------------------------------------

    #[Test]
    public function itIsPositiveReturnsTrueForPositiveValues(): void
    {
        self::assertTrue(Quantity::fromInt(1)->isPositive());
        self::assertTrue(Quantity::fromInt(999999)->isPositive());
    }

    #[Test]
    public function itIsPositiveReturnsFalseForZero(): void
    {
        self::assertFalse(Quantity::zero()->isPositive());
    }

    // ---------------------------------------------------------------
    // toString / __toString
    // ---------------------------------------------------------------

    #[Test]
    public function itToStringAndMagicToStringMatch(): void
    {
        $qty = Quantity::fromInt(123);

        self::assertSame('123', $qty->toString());
        self::assertSame('123', (string) $qty);
    }

    #[Test]
    public function itToStringForZero(): void
    {
        self::assertSame('0', Quantity::zero()->toString());
    }

    // ---------------------------------------------------------------
    // equals
    // ---------------------------------------------------------------

    #[Test]
    public function itEqualsForSameValue(): void
    {
        $a = Quantity::fromInt(42);
        $b = Quantity::fromInt(42);

        self::assertTrue($a->equals($b));
        self::assertTrue($b->equals($a));
    }

    #[Test]
    public function itDoesNotEqualDifferentValue(): void
    {
        $a = Quantity::fromInt(1);
        $b = Quantity::fromInt(2);

        self::assertFalse($a->equals($b));
    }
}
