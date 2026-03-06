<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Infrastructure\Carrier;

use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Infrastructure\Carrier\DhlCarrierAdapter;
use PHPUnit\Framework\TestCase;

final class DhlCarrierAdapterTest extends TestCase
{
    private DhlCarrierAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new DhlCarrierAdapter(apiKey: '', sandbox: true);
    }

    public function testCarrierCodeIsDhl(): void
    {
        $this->assertTrue($this->adapter->carrierCode()->equals(CarrierCode::dhl()));
    }

    public function testIsEnabledInSandboxMode(): void
    {
        $this->assertTrue($this->adapter->isEnabled());
    }

    public function testIsDisabledWithoutApiKeyAndSandbox(): void
    {
        $adapter = new DhlCarrierAdapter(apiKey: '', sandbox: false);
        $this->assertFalse($adapter->isEnabled());
    }

    public function testCalculateRatesReturnsDhlServices(): void
    {
        $rates = $this->adapter->calculateRates('DE', 'DE', 4.0);

        $this->assertCount(3, $rates);
        $serviceNames = array_map(fn ($r) => $r->serviceName(), $rates);
        $this->assertContains('DHL Parcel', $serviceNames);
        $this->assertContains('DHL Express', $serviceNames);
        $this->assertContains('DHL Express Worldwide', $serviceNames);
    }

    public function testInternationalRatesAreHigher(): void
    {
        $domestic = $this->adapter->calculateRates('DE', 'DE', 5.0);
        $international = $this->adapter->calculateRates('DE', 'US', 5.0);

        $this->assertTrue($international[0]->amount()->isGreaterThan($domestic[0]->amount()));
    }

    public function testGeneratesValidTrackingNumber(): void
    {
        $tracking = $this->adapter->generateTrackingNumber();
        $value = $tracking->toString();

        $this->assertMatchesRegularExpression('/^\d{10}$/', $value);
    }
}
