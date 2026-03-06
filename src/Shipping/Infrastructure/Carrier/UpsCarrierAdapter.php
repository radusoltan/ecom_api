<?php

declare(strict_types=1);

namespace App\Shipping\Infrastructure\Carrier;

use App\Shipping\Domain\Service\CarrierAdapterInterface;
use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\ShippingRate;
use App\Shipping\Domain\ValueObject\TrackingNumber;
use Brick\Money\Money;

/**
 * UPS carrier adapter (sandbox/stub mode).
 *
 * In production, this would call the UPS Rating API and Shipping API.
 * Currently returns realistic sandbox rates based on weight and zone.
 */
final readonly class UpsCarrierAdapter implements CarrierAdapterInterface
{
    public function __construct(
        private string $apiKey = '',
        private bool $sandbox = true,
    ) {
    }

    public function carrierCode(): CarrierCode
    {
        return CarrierCode::ups();
    }

    public function isEnabled(): bool
    {
        return $this->sandbox || '' !== $this->apiKey;
    }

    /** @return ShippingRate[] */
    public function calculateRates(string $originCountry, string $destinationCountry, float $weightKg): array
    {
        $isDomestic = $originCountry === $destinationCountry;
        $carrier = CarrierCode::ups();

        $baseRate = $isDomestic ? 8.50 : 25.00;
        $weightSurcharge = $weightKg * ($isDomestic ? 0.75 : 2.50);

        return [
            ShippingRate::create(
                Money::of(round($baseRate + $weightSurcharge, 2), 'USD'),
                $carrier,
                $isDomestic ? 5 : 10,
                'UPS Ground',
            ),
            ShippingRate::create(
                Money::of(round(($baseRate + $weightSurcharge) * 1.8, 2), 'USD'),
                $carrier,
                $isDomestic ? 3 : 5,
                'UPS 3 Day Select',
            ),
            ShippingRate::create(
                Money::of(round(($baseRate + $weightSurcharge) * 3.0, 2), 'USD'),
                $carrier,
                $isDomestic ? 1 : 3,
                'UPS Next Day Air',
            ),
        ];
    }

    public function generateTrackingNumber(): TrackingNumber
    {
        // UPS format: 1Z + 6 alphanumeric shipper + 2 service + 8 package
        $shipper = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $service = sprintf('%02d', random_int(1, 99));
        $package = sprintf('%08d', random_int(0, 99999999));

        return TrackingNumber::fromString("1Z{$shipper}{$service}{$package}");
    }
}
