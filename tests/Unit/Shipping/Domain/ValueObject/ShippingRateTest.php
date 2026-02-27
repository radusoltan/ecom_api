<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Domain\ValueObject;

use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\ShippingRate;
use Brick\Money\Money;
use PHPUnit\Framework\TestCase;

final class ShippingRateTest extends TestCase
{
    public function testCreateValid(): void
    {
        $rate = ShippingRate::create(
            Money::of(10, 'USD'),
            CarrierCode::ups(),
            3,
            'Ground Shipping',
        );

        $this->assertTrue($rate->amount()->isEqualTo(Money::of(10, 'USD')));
        $this->assertTrue($rate->carrier()->equals(CarrierCode::ups()));
        $this->assertSame(3, $rate->estimatedDays());
        $this->assertSame('Ground Shipping', $rate->serviceName());
    }

    public function testRejectsNegativeDays(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Estimated days cannot be negative');

        ShippingRate::create(
            Money::of(5, 'USD'),
            CarrierCode::fedex(),
            -1,
            'Express',
        );
    }

    public function testRejectsEmptyServiceName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Service name cannot be empty');

        ShippingRate::create(
            Money::of(5, 'USD'),
            CarrierCode::fedex(),
            2,
            '',
        );
    }

    public function testRejectsWhitespaceServiceName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Service name cannot be empty');

        ShippingRate::create(
            Money::of(5, 'USD'),
            CarrierCode::fedex(),
            2,
            '   ',
        );
    }

    public function testEquals(): void
    {
        $rate1 = ShippingRate::create(Money::of(10, 'USD'), CarrierCode::ups(), 3, 'Ground');
        $rate2 = ShippingRate::create(Money::of(10, 'USD'), CarrierCode::ups(), 3, 'Ground');
        $rate3 = ShippingRate::create(Money::of(20, 'USD'), CarrierCode::fedex(), 1, 'Express');

        $this->assertTrue($rate1->equals($rate2));
        $this->assertFalse($rate1->equals($rate3));
    }

    public function testZeroDaysAllowed(): void
    {
        $rate = ShippingRate::create(
            Money::of(25, 'USD'),
            CarrierCode::dhl(),
            0,
            'Same Day',
        );

        $this->assertSame(0, $rate->estimatedDays());
    }
}
