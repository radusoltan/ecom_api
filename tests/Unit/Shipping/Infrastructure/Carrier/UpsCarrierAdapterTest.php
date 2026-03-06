<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Infrastructure\Carrier;

use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Infrastructure\Carrier\UpsCarrierAdapter;
use PHPUnit\Framework\TestCase;

final class UpsCarrierAdapterTest extends TestCase
{
    private UpsCarrierAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new UpsCarrierAdapter(apiKey: '', sandbox: true);
    }

    public function testCarrierCodeIsUps(): void
    {
        $this->assertTrue($this->adapter->carrierCode()->equals(CarrierCode::ups()));
    }

    public function testIsEnabledInSandboxMode(): void
    {
        $this->assertTrue($this->adapter->isEnabled());
    }

    public function testIsDisabledWithoutApiKeyAndSandbox(): void
    {
        $adapter = new UpsCarrierAdapter(apiKey: '', sandbox: false);
        $this->assertFalse($adapter->isEnabled());
    }

    public function testIsEnabledWithApiKey(): void
    {
        $adapter = new UpsCarrierAdapter(apiKey: 'test-key', sandbox: false);
        $this->assertTrue($adapter->isEnabled());
    }

    public function testCalculateRatesReturnsDomesticRates(): void
    {
        $rates = $this->adapter->calculateRates('US', 'US', 5.0);

        $this->assertCount(3, $rates);
        foreach ($rates as $rate) {
            $this->assertTrue($rate->carrier()->equals(CarrierCode::ups()));
            $this->assertTrue($rate->amount()->isPositive());
        }

        // Verify services offered
        $serviceNames = array_map(fn ($r) => $r->serviceName(), $rates);
        $this->assertContains('UPS Ground', $serviceNames);
        $this->assertContains('UPS 3 Day Select', $serviceNames);
        $this->assertContains('UPS Next Day Air', $serviceNames);
    }

    public function testInternationalRatesAreHigherThanDomestic(): void
    {
        $domestic = $this->adapter->calculateRates('US', 'US', 5.0);
        $international = $this->adapter->calculateRates('US', 'DE', 5.0);

        $this->assertTrue($international[0]->amount()->isGreaterThan($domestic[0]->amount()));
    }

    public function testHeavierPackageCostsMore(): void
    {
        $light = $this->adapter->calculateRates('US', 'US', 1.0);
        $heavy = $this->adapter->calculateRates('US', 'US', 20.0);

        $this->assertTrue($heavy[0]->amount()->isGreaterThan($light[0]->amount()));
    }

    public function testGeneratesValidTrackingNumber(): void
    {
        $tracking = $this->adapter->generateTrackingNumber();
        $value = $tracking->toString();

        $this->assertStringStartsWith('1Z', $value);
        $this->assertGreaterThanOrEqual(16, strlen($value));
    }
}
