<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Infrastructure\Carrier;

use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\ShippingRate;
use App\Shipping\Infrastructure\Carrier\MockCarrierAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MockCarrierAdapter::class)]
final class MockCarrierAdapterTest extends TestCase
{
    private MockCarrierAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new MockCarrierAdapter();
    }

    // ---------------------------------------------------------------
    // carrierCode()
    // ---------------------------------------------------------------

    public function testCarrierCodeReturnsMock(): void
    {
        $code = $this->adapter->carrierCode();

        $this->assertTrue($code->equals(CarrierCode::mock()));
    }

    public function testCarrierCodeValueIsMock(): void
    {
        $this->assertSame('mock', $this->adapter->carrierCode()->toString());
    }

    // ---------------------------------------------------------------
    // isEnabled()
    // ---------------------------------------------------------------

    public function testIsEnabledReturnsTrue(): void
    {
        $this->assertTrue($this->adapter->isEnabled());
    }

    // ---------------------------------------------------------------
    // calculateRates()
    // ---------------------------------------------------------------

    public function testCalculateRatesReturnsThreeRates(): void
    {
        $rates = $this->adapter->calculateRates('US', 'DE', 2.5);

        $this->assertCount(3, $rates);
    }

    public function testCalculateRatesReturnsShippingRateInstances(): void
    {
        $rates = $this->adapter->calculateRates('US', 'US', 1.0);

        foreach ($rates as $rate) {
            $this->assertInstanceOf(ShippingRate::class, $rate);
        }
    }

    public function testCalculateRatesContainsStandardShipping(): void
    {
        $rates = $this->adapter->calculateRates('US', 'DE', 1.0);
        $names = array_map(fn (ShippingRate $r) => $r->serviceName(), $rates);

        $this->assertContains('Standard Shipping', $names);
    }

    public function testCalculateRatesContainsExpressShipping(): void
    {
        $rates = $this->adapter->calculateRates('US', 'DE', 1.0);
        $names = array_map(fn (ShippingRate $r) => $r->serviceName(), $rates);

        $this->assertContains('Express Shipping', $names);
    }

    public function testCalculateRatesContainsOvernightShipping(): void
    {
        $rates = $this->adapter->calculateRates('US', 'DE', 1.0);
        $names = array_map(fn (ShippingRate $r) => $r->serviceName(), $rates);

        $this->assertContains('Overnight Shipping', $names);
    }

    public function testCalculateRatesAllHaveMockCarrierCode(): void
    {
        $rates = $this->adapter->calculateRates('FR', 'GB', 3.0);

        foreach ($rates as $rate) {
            $this->assertTrue($rate->carrier()->equals(CarrierCode::mock()));
        }
    }

    public function testCalculateRatesStandardCostsNineNinetyNine(): void
    {
        $rates = $this->adapter->calculateRates('US', 'US', 1.0);

        $standard = array_values(array_filter(
            $rates,
            fn (ShippingRate $r) => 'Standard Shipping' === $r->serviceName(),
        ));

        $this->assertCount(1, $standard);
        $this->assertSame('9.99', $standard[0]->amount()->getAmount()->__toString());
    }

    public function testCalculateRatesExpressCostsNineteenNinetyNine(): void
    {
        $rates = $this->adapter->calculateRates('US', 'US', 1.0);

        $express = array_values(array_filter(
            $rates,
            fn (ShippingRate $r) => 'Express Shipping' === $r->serviceName(),
        ));

        $this->assertCount(1, $express);
        $this->assertSame('19.99', $express[0]->amount()->getAmount()->__toString());
    }

    public function testCalculateRatesOvernightCostsTwentyNineNinetyNine(): void
    {
        $rates = $this->adapter->calculateRates('US', 'US', 1.0);

        $overnight = array_values(array_filter(
            $rates,
            fn (ShippingRate $r) => 'Overnight Shipping' === $r->serviceName(),
        ));

        $this->assertCount(1, $overnight);
        $this->assertSame('29.99', $overnight[0]->amount()->getAmount()->__toString());
    }

    public function testCalculateRatesIgnoresInputArguments(): void
    {
        $ratesA = $this->adapter->calculateRates('US', 'DE', 1.0);
        $ratesB = $this->adapter->calculateRates('FR', 'JP', 100.0);

        // Mock adapter returns the same fixed rates regardless of inputs
        $this->assertCount(count($ratesA), $ratesB);

        for ($i = 0, $iMax = count($ratesA); $i < $iMax; ++$i) {
            $this->assertSame(
                $ratesA[$i]->amount()->getAmount()->__toString(),
                $ratesB[$i]->amount()->getAmount()->__toString(),
            );
        }
    }

    // ---------------------------------------------------------------
    // generateTrackingNumber()
    // ---------------------------------------------------------------

    public function testGenerateTrackingNumberStartsWithMock(): void
    {
        $tracking = $this->adapter->generateTrackingNumber();

        $this->assertStringStartsWith('MOCK-', $tracking->toString());
    }

    public function testGenerateTrackingNumberMatchesExpectedFormat(): void
    {
        $tracking = $this->adapter->generateTrackingNumber();

        // Format: MOCK-MOCK-<12 hex chars>
        $this->assertMatchesRegularExpression('/^MOCK-MOCK-[0-9a-f]{12}$/', $tracking->toString());
    }

    public function testGenerateTrackingNumberIsUnique(): void
    {
        $tracking1 = $this->adapter->generateTrackingNumber();
        $tracking2 = $this->adapter->generateTrackingNumber();

        // Two successive calls should produce different tracking numbers
        // (extremely low probability of collision with random_bytes)
        $this->assertFalse($tracking1->equals($tracking2));
    }
}
