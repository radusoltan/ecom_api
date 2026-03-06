<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Money;
use Brick\Money\Exception\MoneyMismatchException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Extended coverage for Money methods not tested in MoneyTest:
 * - amount() (float getter)
 * - currency() (alias for getCurrency)
 * - multiplyBy() (alias for multiply)
 * - isNegative()
 * - isZero()
 * - toArray()
 * - isGreaterThan / isLessThan edge cases with different currencies
 */
#[CoversClass(Money::class)]
final class MoneyExtendedTest extends TestCase
{
    // ---------------------------------------------------------------
    // amount() — returns float representation
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsAmountAsFloat(): void
    {
        $money = Money::fromScalars(1050, 'USD');

        self::assertSame(10.50, $money->amount());
    }

    #[Test]
    public function itReturnsZeroAmountAsFloat(): void
    {
        $money = Money::fromScalars(0, 'EUR');

        self::assertSame(0.0, $money->amount());
    }

    #[Test]
    public function itReturnsNegativeAmountAsFloat(): void
    {
        $money = Money::fromScalars(-500, 'GBP');

        self::assertSame(-5.0, $money->amount());
    }

    #[Test]
    public function itReturnsLargeAmountAsFloat(): void
    {
        $money = Money::of('999999.99', 'USD');

        self::assertSame(999999.99, $money->amount());
    }

    // ---------------------------------------------------------------
    // currency() — alias for getCurrency()
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsCurrencyObjectViaAlias(): void
    {
        $money = Money::fromScalars(1000, 'EUR');

        $currency = $money->currency();

        self::assertSame('EUR', $currency->getCurrencyCode());
    }

    #[Test]
    public function itReturnsSameCurrencyFromBothGetters(): void
    {
        $money = Money::fromScalars(1000, 'GBP');

        self::assertSame(
            $money->getCurrency()->getCurrencyCode(),
            $money->currency()->getCurrencyCode()
        );
    }

    #[Test]
    public function itReturnsCurrencyForJpy(): void
    {
        $money = Money::fromScalars(1000, 'JPY');

        self::assertSame('JPY', $money->currency()->getCurrencyCode());
    }

    // ---------------------------------------------------------------
    // multiplyBy() — delegates to multiply()
    // ---------------------------------------------------------------

    #[Test]
    public function itMultipliesByIntegerViaMultiplyBy(): void
    {
        $money = Money::of('10.00', 'USD');

        $result = $money->multiplyBy(3);

        self::assertSame(3000, $result->getAmount());
    }

    #[Test]
    public function itMultipliesByFloatViaMultiplyBy(): void
    {
        $money = Money::of('20.00', 'EUR');

        $result = $money->multiplyBy(1.5);

        self::assertSame(3000, $result->getAmount());
    }

    #[Test]
    public function itMultipliesByStringViaMultiplyBy(): void
    {
        $money = Money::of('10.00', 'USD');

        $result = $money->multiplyBy('2.5');

        self::assertSame(2500, $result->getAmount());
    }

    #[Test]
    public function itMultipliesByZeroViaMultiplyBy(): void
    {
        $money = Money::of('100.00', 'USD');

        $result = $money->multiplyBy(0);

        self::assertSame(0, $result->getAmount());
    }

    #[Test]
    public function itMultipliesByNegativeViaMultiplyBy(): void
    {
        $money = Money::of('10.00', 'USD');

        $result = $money->multiplyBy(-2);

        self::assertSame(-2000, $result->getAmount());
    }

    #[Test]
    public function multiplyByProducesImmutableResult(): void
    {
        $original = Money::of('50.00', 'USD');

        $result = $original->multiplyBy(2);

        self::assertSame(5000, $original->getAmount());
        self::assertSame(10000, $result->getAmount());
        self::assertNotSame($original, $result);
    }

    // ---------------------------------------------------------------
    // isNegative()
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsTrueForNegativeAmount(): void
    {
        $money = Money::fromScalars(-100, 'USD');

        self::assertTrue($money->isNegative());
    }

    #[Test]
    public function itReturnsFalseForPositiveAmountNotNegative(): void
    {
        $money = Money::fromScalars(100, 'USD');

        self::assertFalse($money->isNegative());
    }

    #[Test]
    public function itReturnsFalseForZeroAmountNotNegative(): void
    {
        $money = Money::fromScalars(0, 'USD');

        self::assertFalse($money->isNegative());
    }

    #[Test]
    public function itReturnsTrueForSmallestNegativeAmount(): void
    {
        $money = Money::fromScalars(-1, 'EUR');

        self::assertTrue($money->isNegative());
    }

    // ---------------------------------------------------------------
    // isZero()
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsTrueForZeroAmount(): void
    {
        $money = Money::fromScalars(0, 'USD');

        self::assertTrue($money->isZero());
    }

    #[Test]
    public function itReturnsTrueForZeroFromOf(): void
    {
        $money = Money::of('0.00', 'EUR');

        self::assertTrue($money->isZero());
    }

    #[Test]
    public function itReturnsFalseForPositiveAmountNotZero(): void
    {
        $money = Money::fromScalars(1, 'USD');

        self::assertFalse($money->isZero());
    }

    #[Test]
    public function itReturnsFalseForNegativeAmountNotZero(): void
    {
        $money = Money::fromScalars(-1, 'USD');

        self::assertFalse($money->isZero());
    }

    #[Test]
    public function resultBecomesZeroAfterSubtractingFullAmount(): void
    {
        $money = Money::of('50.00', 'USD');
        $result = $money->subtract($money);

        self::assertTrue($result->isZero());
    }

    // ---------------------------------------------------------------
    // toArray()
    // ---------------------------------------------------------------

    #[Test]
    public function itConvertsToArrayWithAmountAndCurrency(): void
    {
        $money = Money::fromScalars(1050, 'USD');

        $array = $money->toArray();

        self::assertArrayHasKey('amount', $array);
        self::assertArrayHasKey('currency', $array);
        self::assertSame('1050', $array['amount']);
        self::assertSame('USD', $array['currency']);
    }

    #[Test]
    public function itConvertsZeroAmountToArray(): void
    {
        $money = Money::fromScalars(0, 'EUR');

        $array = $money->toArray();

        self::assertSame('0', $array['amount']);
        self::assertSame('EUR', $array['currency']);
    }

    #[Test]
    public function itConvertsNegativeAmountToArray(): void
    {
        $money = Money::fromScalars(-500, 'GBP');

        $array = $money->toArray();

        self::assertSame('-500', $array['amount']);
        self::assertSame('GBP', $array['currency']);
    }

    #[Test]
    public function itConvertsLargeAmountToArray(): void
    {
        $money = Money::fromScalars(100_000_000, 'USD');

        $array = $money->toArray();

        self::assertSame('100000000', $array['amount']);
        self::assertSame('USD', $array['currency']);
    }

    #[Test]
    public function toArrayOnlyHasTwoKeys(): void
    {
        $money = Money::fromScalars(999, 'JPY');

        $array = $money->toArray();

        self::assertCount(2, $array);
    }

    // ---------------------------------------------------------------
    // Cross-currency error paths
    // ---------------------------------------------------------------

    #[Test]
    public function isGreaterThanThrowsForDifferentCurrencies(): void
    {
        $usd = Money::of('100.00', 'USD');
        $eur = Money::of('100.00', 'EUR');

        $this->expectException(MoneyMismatchException::class);

        $usd->isGreaterThan($eur);
    }

    #[Test]
    public function isLessThanThrowsForDifferentCurrencies(): void
    {
        $usd = Money::of('100.00', 'USD');
        $gbp = Money::of('100.00', 'GBP');

        $this->expectException(MoneyMismatchException::class);

        $usd->isLessThan($gbp);
    }

    #[Test]
    public function addThrowsForDifferentCurrencies(): void
    {
        $usd = Money::of('10.00', 'USD');
        $eur = Money::of('5.00', 'EUR');

        $this->expectException(MoneyMismatchException::class);

        $usd->add($eur);
    }

    #[Test]
    public function subtractThrowsForDifferentCurrencies(): void
    {
        $usd = Money::of('10.00', 'USD');
        $gbp = Money::of('5.00', 'GBP');

        $this->expectException(MoneyMismatchException::class);

        $usd->subtract($gbp);
    }

    // ---------------------------------------------------------------
    // fromScalars with invalid currency
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsForInvalidCurrencyCode(): void
    {
        $this->expectException(\Brick\Money\Exception\UnknownCurrencyException::class);

        Money::fromScalars(1000, 'INVALID');
    }

    // ---------------------------------------------------------------
    // Combination: isZero + isNegative after operations
    // ---------------------------------------------------------------

    #[Test]
    public function itBecomesNegativeAfterSubtraction(): void
    {
        $small = Money::of('5.00', 'USD');
        $large = Money::of('10.00', 'USD');
        $result = $small->subtract($large);

        self::assertTrue($result->isNegative());
        self::assertFalse($result->isZero());
    }

    #[Test]
    public function multiplyByZeroProducesZero(): void
    {
        $money = Money::of('99.99', 'USD');
        $result = $money->multiplyBy(0);

        self::assertTrue($result->isZero());
        self::assertFalse($result->isNegative());
    }
}
