<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class RedemptionRuleTest extends TestCase
{
    public function testCreateRedemptionRule(): void
    {
        $rule = RedemptionRule::create(
            conversionRate: 100.0,
            minPointsToRedeem: 100,
            maxPointsPerOrder: 5000
        );

        $this->assertSame(100.0, $rule->conversionRate());
        $this->assertSame(100, $rule->minPointsToRedeem());
        $this->assertSame(5000, $rule->maxPointsPerOrder());
    }

    public function testCreateWithZeroConversionRateThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Conversion rate must be greater than 0');

        RedemptionRule::create(
            conversionRate: 0.0,
            minPointsToRedeem: 100
        );
    }

    public function testCreateWithNegativeConversionRateThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Conversion rate must be greater than 0');

        RedemptionRule::create(
            conversionRate: -50.0,
            minPointsToRedeem: 100
        );
    }

    public function testCreateWithNegativeMinPointsThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum points to redeem must be greater than or equal to 0');

        RedemptionRule::create(
            conversionRate: 100.0,
            minPointsToRedeem: -1
        );
    }

    public function testCreateWithInvalidMaxPointsThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum points per order must be greater than 0 or null');

        RedemptionRule::create(
            conversionRate: 100.0,
            minPointsToRedeem: 100,
            maxPointsPerOrder: 0
        );
    }

    public function testCreateWithMaxPointsLessThanMinThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum points per order cannot be less than minimum points to redeem');

        RedemptionRule::create(
            conversionRate: 100.0,
            minPointsToRedeem: 500,
            maxPointsPerOrder: 100 // Less than min
        );
    }

    public function testCalculateDiscount(): void
    {
        $rule = RedemptionRule::create(
            conversionRate: 100.0, // 100 points = $1
            minPointsToRedeem: 100
        );

        $discount = $rule->calculateDiscount(500); // 500 points = $5

        $this->assertInstanceOf(Money::class, $discount);
        $this->assertSame(5.0, $discount->amount());
    }

    public function testCalculateDiscountBelowMinimum(): void
    {
        $rule = RedemptionRule::create(
            conversionRate: 100.0,
            minPointsToRedeem: 100
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Points to redeem (50) must be at least 100');

        $rule->calculateDiscount(50); // Below minimum
    }

    public function testCalculateDiscountAboveMaximum(): void
    {
        $rule = RedemptionRule::create(
            conversionRate: 100.0,
            minPointsToRedeem: 100,
            maxPointsPerOrder: 1000
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Points to redeem (1500) cannot exceed maximum of 1000 per order');

        $rule->calculateDiscount(1500); // Above maximum
    }

    public function testEquals(): void
    {
        $rule1 = RedemptionRule::create(100.0, 100, 5000);
        $rule2 = RedemptionRule::create(100.0, 100, 5000);
        $rule3 = RedemptionRule::create(200.0, 200, 10000);

        $this->assertTrue($rule1->equals($rule2));
        $this->assertFalse($rule1->equals($rule3));
    }

    public function testToArray(): void
    {
        $rule = RedemptionRule::create(
            conversionRate: 100.0,
            minPointsToRedeem: 250,
            maxPointsPerOrder: 5000
        );

        $array = $rule->toArray();

        $this->assertIsArray($array);
        $this->assertSame(100.0, $array['conversionRate']);
        $this->assertSame(250, $array['minPointsToRedeem']);
        $this->assertSame(5000, $array['maxPointsPerOrder']);
    }

    public function testToArrayWithNullMaxPoints(): void
    {
        $rule = RedemptionRule::create(
            conversionRate: 100.0,
            minPointsToRedeem: 100,
            maxPointsPerOrder: null
        );

        $array = $rule->toArray();

        $this->assertNull($array['maxPointsPerOrder']);
    }
}
