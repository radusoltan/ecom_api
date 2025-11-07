<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\ValueObject;

use App\Pricing\Domain\ValueObject\Discount;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class DiscountTest extends TestCase
{
    public function testPercentageCreatesPercentageDiscount(): void
    {
        $discount = Discount::percentage(10.0);

        $this->assertTrue($discount->type()->isPercentage());
        $this->assertSame(10.0, $discount->value());
    }

    public function testFixedAmountCreatesFixedDiscount(): void
    {
        $discount = Discount::fixedAmount(5.50);

        $this->assertTrue($discount->type()->isFixedAmount());
        $this->assertSame(5.50, $discount->value());
    }

    public function testPercentageValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Percentage discount must be between 0.01 and 100');

        Discount::percentage(150.0);
    }

    public function testPercentageTooLowThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Discount::percentage(0.0);
    }

    public function testFixedAmountValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Fixed amount discount must be greater than 0.01');

        Discount::fixedAmount(0.0);
    }

    public function testApplyToWithPercentageDiscount(): void
    {
        $discount = Discount::percentage(20.0);
        $price = Money::of('100', 'USD');

        $discountAmount = $discount->applyTo($price);

        $this->assertSame(2000, $discountAmount->getAmount()); // 20.00 USD = 2000 cents
    }

    public function testApplyToWithFixedAmountDiscount(): void
    {
        $discount = Discount::fixedAmount(15.0);
        $price = Money::of('100', 'USD');

        $discountAmount = $discount->applyTo($price);

        $this->assertSame(1500, $discountAmount->getAmount()); // 15.00 USD = 1500 cents
    }

    public function testApplyToDoesNotExceedPrice(): void
    {
        $discount = Discount::fixedAmount(150.0);
        $price = Money::of('100', 'USD');

        $discountAmount = $discount->applyTo($price);

        $this->assertSame(10000, $discountAmount->getAmount()); // 100.00 USD = 10000 cents (capped at price)
    }

    public function testApplyToWith50PercentDiscount(): void
    {
        $discount = Discount::percentage(50.0);
        $price = Money::of('200', 'EUR');

        $discountAmount = $discount->applyTo($price);

        $this->assertSame(10000, $discountAmount->getAmount()); // 100.00 EUR = 10000 cents
        $this->assertSame('EUR', $discountAmount->getCurrency()->getCurrencyCode());
    }

    public function testFromTypeAndValue(): void
    {
        $discount = Discount::fromTypeAndValue('percentage', 25.0);

        $this->assertTrue($discount->type()->isPercentage());
        $this->assertSame(25.0, $discount->value());
    }

    public function testEqualsReturnsTrueForSameDiscount(): void
    {
        $discount1 = Discount::percentage(15.0);
        $discount2 = Discount::percentage(15.0);

        $this->assertTrue($discount1->equals($discount2));
    }

    public function testEqualsReturnsFalseForDifferentValues(): void
    {
        $discount1 = Discount::percentage(15.0);
        $discount2 = Discount::percentage(20.0);

        $this->assertFalse($discount1->equals($discount2));
    }

    public function testEqualsReturnsFalseForDifferentTypes(): void
    {
        $discount1 = Discount::percentage(15.0);
        $discount2 = Discount::fixedAmount(15.0);

        $this->assertFalse($discount1->equals($discount2));
    }
}
