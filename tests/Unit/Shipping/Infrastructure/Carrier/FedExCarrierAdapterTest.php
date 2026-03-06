<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Infrastructure\Carrier;

use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Infrastructure\Carrier\FedExCarrierAdapter;
use PHPUnit\Framework\TestCase;

final class FedExCarrierAdapterTest extends TestCase
{
    private FedExCarrierAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new FedExCarrierAdapter(apiKey: '', sandbox: true);
    }

    public function testCarrierCodeIsFedex(): void
    {
        $this->assertTrue($this->adapter->carrierCode()->equals(CarrierCode::fedex()));
    }

    public function testIsEnabledInSandboxMode(): void
    {
        $this->assertTrue($this->adapter->isEnabled());
    }

    public function testIsDisabledWithoutApiKeyAndSandbox(): void
    {
        $adapter = new FedExCarrierAdapter(apiKey: '', sandbox: false);
        $this->assertFalse($adapter->isEnabled());
    }

    public function testCalculateRatesReturnsFedExServices(): void
    {
        $rates = $this->adapter->calculateRates('US', 'US', 3.0);

        $this->assertCount(3, $rates);
        $serviceNames = array_map(fn ($r) => $r->serviceName(), $rates);
        $this->assertContains('FedEx Ground', $serviceNames);
        $this->assertContains('FedEx Express Saver', $serviceNames);
        $this->assertContains('FedEx Priority Overnight', $serviceNames);
    }

    public function testInternationalRatesAreHigher(): void
    {
        $domestic = $this->adapter->calculateRates('US', 'US', 5.0);
        $international = $this->adapter->calculateRates('US', 'GB', 5.0);

        $this->assertTrue($international[0]->amount()->isGreaterThan($domestic[0]->amount()));
    }

    public function testGeneratesValidTrackingNumber(): void
    {
        $tracking = $this->adapter->generateTrackingNumber();
        $value = $tracking->toString();

        $this->assertMatchesRegularExpression('/^\d{15}$/', $value);
    }
}
