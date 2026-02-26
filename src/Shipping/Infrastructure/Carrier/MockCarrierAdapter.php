<?php

declare(strict_types=1);

namespace App\Shipping\Infrastructure\Carrier;

use App\Shipping\Domain\Service\ShippingRateCalculatorInterface;
use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\ShippingRate;
use App\Shipping\Domain\ValueObject\TrackingNumber;
use Brick\Money\Money;

final class MockCarrierAdapter implements ShippingRateCalculatorInterface
{
    /** @return ShippingRate[] */
    public function calculateRates(string $originCountry, string $destinationCountry, float $weightKg): array
    {
        $carrier = CarrierCode::mock();

        return [
            ShippingRate::create(
                Money::of('9.99', 'USD'),
                $carrier,
                7,
                'Standard Shipping',
            ),
            ShippingRate::create(
                Money::of('19.99', 'USD'),
                $carrier,
                3,
                'Express Shipping',
            ),
            ShippingRate::create(
                Money::of('29.99', 'USD'),
                $carrier,
                1,
                'Overnight Shipping',
            ),
        ];
    }

    public function generateTrackingNumber(CarrierCode $carrier): TrackingNumber
    {
        return TrackingNumber::fromString(sprintf('MOCK-%s-%s', strtoupper($carrier->toString()), bin2hex(random_bytes(6))));
    }
}
