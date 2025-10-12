<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Money;
use Brick\Money\Exception\UnknownCurrencyException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testFromScalarsCreatesMoneyFromCents(): void
    {
        $money = Money::fromScalars(1000, 'USD');

        $this->assertSame(1000, $money->getAmount());
        $this->assertSame('USD', $money->getCurrency()->getCurrencyCode());
    }

    public function testOfCreatesMoneyFromDecimalString(): void
    {
        $money = Money::of('10.50', 'EUR');

        $this->assertSame(1050, $money->getAmount()); // Stored in cents
        $this->assertSame('EUR', $money->getCurrency()->getCurrencyCode());
    }

    public function testOfWithWholeNumber(): void
    {
        $money = Money::of('100', 'GBP');

        $this->assertSame(10000, $money->getAmount());
        $this->assertSame('GBP', $money->getCurrency()->getCurrencyCode());
    }

    public function testOfWithZero(): void
    {
        $money = Money::of('0', 'USD');

        $this->assertSame(0, $money->getAmount());
        $this->assertSame('USD', $money->getCurrency()->getCurrencyCode());
    }

    public function testOfWithVerySmallAmount(): void
    {
        $money = Money::of('0.01', 'USD');

        $this->assertSame(1, $money->getAmount());
    }

    public function testOfWithLargeAmount(): void
    {
        $money = Money::of('999999.99', 'USD');

        $this->assertSame(99999999, $money->getAmount());
    }

    public function testFromScalarsWithZero(): void
    {
        $money = Money::fromScalars(0, 'EUR');

        $this->assertSame(0, $money->getAmount());
    }

    public function testFromScalarsWithNegativeAmount(): void
    {
        $money = Money::fromScalars(-500, 'USD');

        $this->assertSame(-500, $money->getAmount());
    }

    public function testOfThrowsExceptionForInvalidCurrency(): void
    {
        $this->expectException(UnknownCurrencyException::class);

        Money::of('10.00', 'INVALID');
    }

    public function testAddTwoMoneyValues(): void
    {
        $money1 = Money::of('10.00', 'USD');
        $money2 = Money::of('5.50', 'USD');

        $result = $money1->add($money2);

        $this->assertSame(1550, $result->getAmount()); // 15.50 in cents
        $this->assertSame('USD', $result->getCurrency()->getCurrencyCode());
    }

    public function testSubtractTwoMoneyValues(): void
    {
        $money1 = Money::of('10.00', 'USD');
        $money2 = Money::of('3.50', 'USD');

        $result = $money1->subtract($money2);

        $this->assertSame(650, $result->getAmount()); // 6.50 in cents
    }

    public function testSubtractResultingInNegative(): void
    {
        $money1 = Money::of('5.00', 'USD');
        $money2 = Money::of('10.00', 'USD');

        $result = $money1->subtract($money2);

        $this->assertSame(-500, $result->getAmount()); // -5.00 in cents
    }

    public function testMultiplyByInteger(): void
    {
        $money = Money::of('10.00', 'USD');

        $result = $money->multiply(3);

        $this->assertSame(3000, $result->getAmount()); // 30.00 in cents
    }

    public function testMultiplyByFloat(): void
    {
        $money = Money::of('10.00', 'USD');

        $result = $money->multiply(1.5);

        $this->assertSame(1500, $result->getAmount()); // 15.00 in cents
    }

    public function testMultiplyByString(): void
    {
        $money = Money::of('10.00', 'USD');

        $result = $money->multiply('2.5');

        $this->assertSame(2500, $result->getAmount()); // 25.00 in cents
    }

    public function testMultiplyByZero(): void
    {
        $money = Money::of('100.00', 'USD');

        $result = $money->multiply(0);

        $this->assertSame(0, $result->getAmount());
    }

    public function testMultiplyByNegative(): void
    {
        $money = Money::of('10.00', 'USD');

        $result = $money->multiply(-2);

        $this->assertSame(-2000, $result->getAmount()); // -20.00 in cents
    }

    public function testIsGreaterThanReturnsTrueWhenGreater(): void
    {
        $money1 = Money::of('100.00', 'USD');
        $money2 = Money::of('50.00', 'USD');

        $this->assertTrue($money1->isGreaterThan($money2));
    }

    public function testIsGreaterThanReturnsFalseWhenLess(): void
    {
        $money1 = Money::of('50.00', 'USD');
        $money2 = Money::of('100.00', 'USD');

        $this->assertFalse($money1->isGreaterThan($money2));
    }

    public function testIsGreaterThanReturnsFalseWhenEqual(): void
    {
        $money1 = Money::of('100.00', 'USD');
        $money2 = Money::of('100.00', 'USD');

        $this->assertFalse($money1->isGreaterThan($money2));
    }

    public function testIsLessThanReturnsTrueWhenLess(): void
    {
        $money1 = Money::of('50.00', 'USD');
        $money2 = Money::of('100.00', 'USD');

        $this->assertTrue($money1->isLessThan($money2));
    }

    public function testIsLessThanReturnsFalseWhenGreater(): void
    {
        $money1 = Money::of('100.00', 'USD');
        $money2 = Money::of('50.00', 'USD');

        $this->assertFalse($money1->isLessThan($money2));
    }

    public function testIsLessThanReturnsFalseWhenEqual(): void
    {
        $money1 = Money::of('100.00', 'USD');
        $money2 = Money::of('100.00', 'USD');

        $this->assertFalse($money1->isLessThan($money2));
    }

    public function testEqualsReturnsTrueForSameAmount(): void
    {
        $money1 = Money::of('100.00', 'USD');
        $money2 = Money::of('100.00', 'USD');

        $this->assertTrue($money1->equals($money2));
    }

    public function testEqualsReturnsFalseForDifferentAmount(): void
    {
        $money1 = Money::of('100.00', 'USD');
        $money2 = Money::of('50.00', 'USD');

        $this->assertFalse($money1->equals($money2));
    }

    public function testEqualsReturnsFalseForDifferentCurrency(): void
    {
        $money1 = Money::of('100.00', 'USD');
        $money2 = Money::of('100.00', 'EUR');

        // Brick Money throws exception when comparing different currencies
        $this->expectException(\Brick\Money\Exception\MoneyMismatchException::class);

        $money1->equals($money2);
    }

    public function testEqualsWithZeroAmounts(): void
    {
        $money1 = Money::of('0', 'USD');
        $money2 = Money::of('0.00', 'USD');

        $this->assertTrue($money1->equals($money2));
    }

    public function testToStringFormatsMoneyCorrectly(): void
    {
        $money = Money::of('1234.56', 'USD');

        $formatted = (string) $money;

        // formatTo('en_US') returns formatted string with currency symbol
        $this->assertStringContainsString('1,234.56', $formatted);
        $this->assertNotEmpty($formatted);
    }

    public function testToStringWithZero(): void
    {
        $money = Money::of('0', 'EUR');

        $formatted = (string) $money;

        // formatTo('en_US') returns formatted string
        $this->assertStringContainsString('0', $formatted);
        $this->assertNotEmpty($formatted);
    }

    public function testDifferentCurrencyCodes(): void
    {
        $usd = Money::of('10.00', 'USD');
        $eur = Money::of('10.00', 'EUR');
        $gbp = Money::of('10.00', 'GBP');
        $jpy = Money::of('1000', 'JPY');

        $this->assertSame('USD', $usd->getCurrency()->getCurrencyCode());
        $this->assertSame('EUR', $eur->getCurrency()->getCurrencyCode());
        $this->assertSame('GBP', $gbp->getCurrency()->getCurrencyCode());
        $this->assertSame('JPY', $jpy->getCurrency()->getCurrencyCode());
    }

    public function testFromScalarsWithVeryLargeAmount(): void
    {
        $money = Money::fromScalars(999999999, 'USD');

        $this->assertSame(999999999, $money->getAmount());
    }

    public function testChainedOperations(): void
    {
        $money = Money::of('10.00', 'USD');

        $result = $money
            ->add(Money::of('5.00', 'USD'))
            ->multiply(2)
            ->subtract(Money::of('10.00', 'USD'));

        $this->assertSame(2000, $result->getAmount()); // (10 + 5) * 2 - 10 = 20
    }

    public function testImmutabilityAfterAdd(): void
    {
        $original = Money::of('10.00', 'USD');
        $other = Money::of('5.00', 'USD');

        $result = $original->add($other);

        // Original should remain unchanged
        $this->assertSame(1000, $original->getAmount());
        $this->assertSame(1500, $result->getAmount());
        $this->assertNotSame($original, $result);
    }

    public function testImmutabilityAfterSubtract(): void
    {
        $original = Money::of('10.00', 'USD');
        $other = Money::of('3.00', 'USD');

        $result = $original->subtract($other);

        // Original should remain unchanged
        $this->assertSame(1000, $original->getAmount());
        $this->assertSame(700, $result->getAmount());
        $this->assertNotSame($original, $result);
    }

    public function testImmutabilityAfterMultiply(): void
    {
        $original = Money::of('10.00', 'USD');

        $result = $original->multiply(2);

        // Original should remain unchanged
        $this->assertSame(1000, $original->getAmount());
        $this->assertSame(2000, $result->getAmount());
        $this->assertNotSame($original, $result);
    }

    public function testComparisonWithNegativeAmounts(): void
    {
        $positive = Money::of('10.00', 'USD');
        $negative = Money::of('-5.00', 'USD');

        $this->assertTrue($positive->isGreaterThan($negative));
        $this->assertTrue($negative->isLessThan($positive));
        $this->assertFalse($positive->equals($negative));
    }

    public function testAddingZeroDoesNotChangeAmount(): void
    {
        $money = Money::of('100.00', 'USD');
        $zero = Money::of('0', 'USD');

        $result = $money->add($zero);

        $this->assertTrue($money->equals($result));
    }

    public function testSubtractingZeroDoesNotChangeAmount(): void
    {
        $money = Money::of('100.00', 'USD');
        $zero = Money::of('0', 'USD');

        $result = $money->subtract($zero);

        $this->assertTrue($money->equals($result));
    }

    public function testMultiplyingByOneDoesNotChangeAmount(): void
    {
        $money = Money::of('100.00', 'USD');

        $result = $money->multiply(1);

        $this->assertTrue($money->equals($result));
    }
}
