<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\EarningRate;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class EarningRateTest extends TestCase
{
    public function testCreateEarningRate(): void
    {
        $rate = EarningRate::fromFloat(1.5);

        $this->assertSame(1.5, $rate->toFloat());
        $this->assertSame('1.50', (string) $rate);
    }

    public function testCreateWithZeroThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Earning rate must be greater than 0');

        EarningRate::fromFloat(0.0);
    }

    public function testCreateWithNegativeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Earning rate must be greater than 0');

        EarningRate::fromFloat(-1.5);
    }

    public function testCreateWithTooHighThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Earning rate cannot exceed 100 points per currency unit');

        EarningRate::fromFloat(100.1);
    }

    public function testCalculatePoints(): void
    {
        $rate = EarningRate::fromFloat(2.0);
        $amount = Money::of('100', 'USD');

        $points = $rate->calculatePoints($amount);

        $this->assertSame(200, $points);
    }

    public function testCalculatePointsRoundsDown(): void
    {
        $rate = EarningRate::fromFloat(1.5);
        $amount = Money::of('99', 'USD'); // 99 * 1.5 = 148.5

        $points = $rate->calculatePoints($amount);

        $this->assertSame(148, $points); // Should round down to 148
    }

    public function testEquals(): void
    {
        $rate1 = EarningRate::fromFloat(1.5);
        $rate2 = EarningRate::fromFloat(1.5);
        $rate3 = EarningRate::fromFloat(2.0);

        $this->assertTrue($rate1->equals($rate2));
        $this->assertFalse($rate1->equals($rate3));
    }

    public function testToFloat(): void
    {
        $rate = EarningRate::fromFloat(3.75);

        $this->assertSame(3.75, $rate->toFloat());
    }

    public function testFromString(): void
    {
        $rate = EarningRate::fromString('2.50');

        $this->assertSame(2.5, $rate->toFloat());
    }

    public function testFromStringWithInteger(): void
    {
        $rate = EarningRate::fromString('5');

        $this->assertSame(5.0, $rate->toFloat());
    }

    public function testToString(): void
    {
        $rate = EarningRate::fromFloat(1.5);

        $this->assertSame('1.50', (string) $rate);
    }
}
