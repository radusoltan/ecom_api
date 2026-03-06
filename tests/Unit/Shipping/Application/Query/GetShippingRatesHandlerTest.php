<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Application\Query;

use App\Shipping\Application\Query\GetShippingRates\GetShippingRatesHandler;
use App\Shipping\Application\Query\GetShippingRates\GetShippingRatesQuery;
use App\Shipping\Domain\Service\ShippingRateCalculatorInterface;
use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\ShippingRate;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetShippingRatesHandler::class)]
final class GetShippingRatesHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    // ---------------------------------------------------------------
    // Delegation to calculator
    // ---------------------------------------------------------------

    public function testInvokeDelegatesToCalculateRates(): void
    {
        $calculator = $this->createMock(ShippingRateCalculatorInterface::class);
        $calculator->expects($this->once())
            ->method('calculateRates')
            ->with('DE', 'US', 3.5)
            ->willReturn([]);

        $handler = new GetShippingRatesHandler($calculator);

        $query = new GetShippingRatesQuery(
            originCountry: 'DE',
            destinationCountry: 'US',
            weightKg: 3.5,
            tenantId: self::TENANT_ID,
        );

        $result = $handler($query);

        $this->assertSame([], $result);
    }

    public function testInvokeForwardsOriginCountryCorrectly(): void
    {
        $calculator = $this->createMock(ShippingRateCalculatorInterface::class);
        $calculator->expects($this->once())
            ->method('calculateRates')
            ->with('FR', $this->anything(), $this->anything())
            ->willReturn([]);

        $handler = new GetShippingRatesHandler($calculator);

        $handler(new GetShippingRatesQuery(
            originCountry: 'FR',
            destinationCountry: 'GB',
            weightKg: 1.0,
            tenantId: self::TENANT_ID,
        ));
    }

    public function testInvokeForwardsDestinationCountryCorrectly(): void
    {
        $calculator = $this->createMock(ShippingRateCalculatorInterface::class);
        $calculator->expects($this->once())
            ->method('calculateRates')
            ->with($this->anything(), 'JP', $this->anything())
            ->willReturn([]);

        $handler = new GetShippingRatesHandler($calculator);

        $handler(new GetShippingRatesQuery(
            originCountry: 'US',
            destinationCountry: 'JP',
            weightKg: 2.0,
            tenantId: self::TENANT_ID,
        ));
    }

    public function testInvokeForwardsWeightCorrectly(): void
    {
        $calculator = $this->createMock(ShippingRateCalculatorInterface::class);
        $calculator->expects($this->once())
            ->method('calculateRates')
            ->with($this->anything(), $this->anything(), 12.75)
            ->willReturn([]);

        $handler = new GetShippingRatesHandler($calculator);

        $handler(new GetShippingRatesQuery(
            originCountry: 'US',
            destinationCountry: 'CA',
            weightKg: 12.75,
            tenantId: self::TENANT_ID,
        ));
    }

    // ---------------------------------------------------------------
    // Return value passthrough
    // ---------------------------------------------------------------

    public function testInvokeReturnsCalculatorRates(): void
    {
        $carrier = CarrierCode::mock();
        $expectedRates = [
            ShippingRate::create(Money::of('9.99', 'USD'), $carrier, 7, 'Standard'),
            ShippingRate::create(Money::of('19.99', 'USD'), $carrier, 3, 'Express'),
        ];

        $calculator = $this->createMock(ShippingRateCalculatorInterface::class);
        $calculator->method('calculateRates')->willReturn($expectedRates);

        $handler = new GetShippingRatesHandler($calculator);

        $result = $handler(new GetShippingRatesQuery(
            originCountry: 'US',
            destinationCountry: 'US',
            weightKg: 5.0,
            tenantId: self::TENANT_ID,
        ));

        $this->assertCount(2, $result);
        $this->assertSame($expectedRates[0], $result[0]);
        $this->assertSame($expectedRates[1], $result[1]);
    }

    public function testInvokeReturnsEmptyArrayWhenNoRates(): void
    {
        $calculator = $this->createMock(ShippingRateCalculatorInterface::class);
        $calculator->method('calculateRates')->willReturn([]);

        $handler = new GetShippingRatesHandler($calculator);

        $result = $handler(new GetShippingRatesQuery(
            originCountry: 'US',
            destinationCountry: 'US',
            weightKg: 0.1,
            tenantId: self::TENANT_ID,
        ));

        $this->assertSame([], $result);
    }
}
