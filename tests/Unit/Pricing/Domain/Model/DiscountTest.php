<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\Model;

use App\Pricing\Domain\Model\Discount;
use App\Shared\Domain\ValueObject\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DiscountTest extends TestCase
{
    public function testPercentageCreatesValidDiscount(): void
    {
        $discount = Discount::percentage(25.5);

        $this->assertInstanceOf(Discount::class, $discount);
        $this->assertSame('percentage', $discount->getType());
        $this->assertSame(25.5, $discount->getPercentage());
        $this->assertNull($discount->getFixedAmount());
    }

    public function testFixedCreatesValidDiscount(): void
    {
        $amount = Money::of('10.00', 'USD');
        $discount = Discount::fixed($amount);

        $this->assertInstanceOf(Discount::class, $discount);
        $this->assertSame('fixed', $discount->getType());
        $this->assertNull($discount->getPercentage());
        $this->assertTrue($discount->getFixedAmount()->equals($amount));
    }

    public function testPercentageThrowsExceptionForNegativeValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount percentage must be between 0 and 100');

        Discount::percentage(-5.0);
    }

    public function testPercentageThrowsExceptionForValueOverHundred(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount percentage must be between 0 and 100');

        Discount::percentage(101.0);
    }

    public function testPercentageAcceptsZero(): void
    {
        $discount = Discount::percentage(0.0);

        $this->assertSame(0.0, $discount->getPercentage());
    }

    public function testPercentageAcceptsHundred(): void
    {
        $discount = Discount::percentage(100.0);

        $this->assertSame(100.0, $discount->getPercentage());
    }

    public function testApplyPercentageDiscountToPrice(): void
    {
        $discount = Discount::percentage(20.0);
        $price = Money::of('100.00', 'USD');

        $discountedPrice = $discount->apply($price);

        $this->assertSame(8000, $discountedPrice->getAmount()); // 100 - 20 = 80.00
    }

    public function testApplyFixedDiscountToPrice(): void
    {
        $discount = Discount::fixed(Money::of('15.00', 'USD'));
        $price = Money::of('100.00', 'USD');

        $discountedPrice = $discount->apply($price);

        $this->assertSame(8500, $discountedPrice->getAmount()); // 100 - 15 = 85.00
    }

    public function testApplyHundredPercentDiscountResultsInZero(): void
    {
        $discount = Discount::percentage(100.0);
        $price = Money::of('50.00', 'USD');

        $discountedPrice = $discount->apply($price);

        $this->assertSame(0, $discountedPrice->getAmount());
    }

    public function testCalculateDiscountAmountForPercentage(): void
    {
        $discount = Discount::percentage(25.0);
        $price = Money::of('80.00', 'USD');

        $discountAmount = $discount->calculateDiscountAmount($price);

        $this->assertSame(2000, $discountAmount->getAmount()); // 25% of 80 = 20.00
    }

    public function testCalculateDiscountAmountForFixedDiscount(): void
    {
        $fixedAmount = Money::of('10.00', 'EUR');
        $discount = Discount::fixed($fixedAmount);
        $price = Money::of('100.00', 'EUR');

        $discountAmount = $discount->calculateDiscountAmount($price);

        $this->assertSame(1000, $discountAmount->getAmount());
    }

    public function testToArrayForPercentageDiscount(): void
    {
        $discount = Discount::percentage(15.5);

        $array = $discount->toArray();

        $this->assertSame([
            'type' => 'percentage',
            'percentage' => 15.5,
            'fixed_amount' => null,
        ], $array);
    }

    public function testToArrayForFixedDiscount(): void
    {
        $amount = Money::of('25.00', 'USD');
        $discount = Discount::fixed($amount);

        $array = $discount->toArray();

        $this->assertSame('fixed', $array['type']);
        $this->assertNull($array['percentage']);
        $this->assertIsArray($array['fixed_amount']);
        $this->assertSame(2500, (int)$array['fixed_amount']['amount']);
        $this->assertSame('USD', $array['fixed_amount']['currency']);
    }

    public function testPercentageDiscountWithDecimalPlaces(): void
    {
        $discount = Discount::percentage(12.75);
        $price = Money::of('100.00', 'USD');

        $discountedPrice = $discount->apply($price);

        // 100 - (100 * 0.1275) = 87.25
        $this->assertSame(8725, $discountedPrice->getAmount());
    }

    public function testFixedDiscountGreaterThanPriceResultsInZero(): void
    {
        $discount = Discount::fixed(Money::of('150.00', 'USD'));
        $price = Money::of('100.00', 'USD');

        $discountedPrice = $discount->apply($price);

        // Fixed discount cannot result in negative price
        $this->assertSame(0, $discountedPrice->getAmount());
    }

    public function testApplyZeroPercentageDiscount(): void
    {
        $discount = Discount::percentage(0.0);
        $price = Money::of('50.00', 'USD');

        $discountedPrice = $discount->apply($price);

        $this->assertSame(5000, $discountedPrice->getAmount());
    }

    public function testApplyZeroFixedDiscount(): void
    {
        $discount = Discount::fixed(Money::of('0.00', 'USD'));
        $price = Money::of('50.00', 'USD');

        $discountedPrice = $discount->apply($price);

        $this->assertSame(5000, $discountedPrice->getAmount());
    }
}
