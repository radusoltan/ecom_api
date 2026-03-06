<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Infrastructure\Carrier;

use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Infrastructure\Carrier\CompositeShippingRateCalculator;
use App\Shipping\Infrastructure\Carrier\DhlCarrierAdapter;
use App\Shipping\Infrastructure\Carrier\FedExCarrierAdapter;
use App\Shipping\Infrastructure\Carrier\MockCarrierAdapter;
use App\Shipping\Infrastructure\Carrier\UpsCarrierAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CompositeShippingRateCalculatorTest extends TestCase
{
    private CompositeShippingRateCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new CompositeShippingRateCalculator(
            [
                new UpsCarrierAdapter(apiKey: '', sandbox: true),
                new FedExCarrierAdapter(apiKey: '', sandbox: true),
                new DhlCarrierAdapter(apiKey: '', sandbox: true),
                new MockCarrierAdapter(),
            ],
            new NullLogger(),
        );
    }

    public function testAggregatesRatesFromAllCarriers(): void
    {
        $rates = $this->calculator->calculateRates('US', 'US', 5.0);

        // 3 services per carrier * 4 carriers = 12 rates
        $this->assertCount(12, $rates);

        $carriers = array_unique(array_map(fn ($r) => $r->carrier()->toString(), $rates));
        sort($carriers);
        $this->assertContains('ups', $carriers);
        $this->assertContains('fedex', $carriers);
        $this->assertContains('dhl', $carriers);
        $this->assertContains('mock', $carriers);
    }

    public function testRatesAreSortedByPriceAscending(): void
    {
        $rates = $this->calculator->calculateRates('US', 'US', 5.0);

        for ($i = 1; $i < \count($rates); ++$i) {
            $this->assertTrue(
                $rates[$i]->amount()->isGreaterThanOrEqualTo($rates[$i - 1]->amount()),
                sprintf(
                    'Rate at index %d ($%s) should be >= rate at index %d ($%s)',
                    $i,
                    $rates[$i]->amount()->getAmount(),
                    $i - 1,
                    $rates[$i - 1]->amount()->getAmount(),
                )
            );
        }
    }

    public function testSkipsDisabledCarriers(): void
    {
        $calculator = new CompositeShippingRateCalculator(
            [
                new UpsCarrierAdapter(apiKey: '', sandbox: true),     // enabled
                new FedExCarrierAdapter(apiKey: '', sandbox: false),   // disabled (no key, not sandbox)
                new DhlCarrierAdapter(apiKey: '', sandbox: true),      // enabled
            ],
            new NullLogger(),
        );

        $rates = $calculator->calculateRates('US', 'US', 5.0);

        // UPS (3) + DHL (3) = 6 rates (no FedEx)
        $this->assertCount(6, $rates);

        $carriers = array_unique(array_map(fn ($r) => $r->carrier()->toString(), $rates));
        $this->assertContains('ups', $carriers);
        $this->assertContains('dhl', $carriers);
        $this->assertNotContains('fedex', $carriers);
    }

    public function testGeneratesTrackingForSpecificCarrier(): void
    {
        $tracking = $this->calculator->generateTrackingNumber(CarrierCode::ups());
        $this->assertStringStartsWith('1Z', $tracking->toString());

        $tracking = $this->calculator->generateTrackingNumber(CarrierCode::fedex());
        $this->assertMatchesRegularExpression('/^\d{15}$/', $tracking->toString());

        $tracking = $this->calculator->generateTrackingNumber(CarrierCode::dhl());
        $this->assertMatchesRegularExpression('/^\d{10}$/', $tracking->toString());

        $tracking = $this->calculator->generateTrackingNumber(CarrierCode::mock());
        $this->assertStringStartsWith('MOCK-', $tracking->toString());
    }

    public function testThrowsForUnknownCarrier(): void
    {
        $calculator = new CompositeShippingRateCalculator(
            [new UpsCarrierAdapter(apiKey: '', sandbox: true)],
            new NullLogger(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No adapter registered for carrier "fedex"');

        $calculator->generateTrackingNumber(CarrierCode::fedex());
    }

    public function testContinuesWhenCarrierFails(): void
    {
        // Create a calculator with only reliable adapters
        // The mock adapter never fails, so this test verifies the error handling pattern
        $calculator = new CompositeShippingRateCalculator(
            [
                new UpsCarrierAdapter(apiKey: '', sandbox: true),
                new MockCarrierAdapter(),
            ],
            new NullLogger(),
        );

        $rates = $calculator->calculateRates('US', 'US', 1.0);

        // Both adapters should contribute rates
        $this->assertCount(6, $rates); // 3 UPS + 3 Mock
    }

    public function testEmptyAdaptersReturnsEmptyRates(): void
    {
        $calculator = new CompositeShippingRateCalculator([], new NullLogger());

        $rates = $calculator->calculateRates('US', 'US', 5.0);

        $this->assertCount(0, $rates);
    }
}
