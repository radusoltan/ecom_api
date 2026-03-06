<?php

declare(strict_types=1);

namespace App\Shipping\Infrastructure\Carrier;

use App\Shipping\Domain\Service\CarrierAdapterInterface;
use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\ShippingRate;
use App\Shipping\Domain\ValueObject\TrackingNumber;
use Brick\Money\Money;

/**
 * DHL carrier adapter (sandbox/stub mode).
 *
 * In production, this would call the DHL Express Rate and Shipment APIs.
 * Currently returns realistic sandbox rates based on weight and zone.
 */
final readonly class DhlCarrierAdapter implements CarrierAdapterInterface
{
    public function __construct(
        private string $apiKey = '',
        private bool $sandbox = true,
    ) {
    }

    public function carrierCode(): CarrierCode
    {
        return CarrierCode::dhl();
    }

    public function isEnabled(): bool
    {
        return $this->sandbox || '' !== $this->apiKey;
    }

    /** @return ShippingRate[] */
    public function calculateRates(string $originCountry, string $destinationCountry, float $weightKg): array
    {
        $isDomestic = $originCountry === $destinationCountry;
        $carrier = CarrierCode::dhl();

        // DHL is typically more competitive internationally
        $baseRate = $isDomestic ? 9.50 : 22.00;
        $weightSurcharge = $weightKg * ($isDomestic ? 0.85 : 2.20);

        return [
            ShippingRate::create(
                Money::of(round($baseRate + $weightSurcharge, 2), 'USD'),
                $carrier,
                $isDomestic ? 5 : 7,
                'DHL Parcel',
            ),
            ShippingRate::create(
                Money::of(round(($baseRate + $weightSurcharge) * 1.7, 2), 'USD'),
                $carrier,
                $isDomestic ? 3 : 4,
                'DHL Express',
            ),
            ShippingRate::create(
                Money::of(round(($baseRate + $weightSurcharge) * 2.5, 2), 'USD'),
                $carrier,
                $isDomestic ? 1 : 2,
                'DHL Express Worldwide',
            ),
        ];
    }

    public function generateTrackingNumber(): TrackingNumber
    {
        // DHL format: 10 digits
        $number = sprintf('%010d', random_int(1000000000, 9999999999));

        return TrackingNumber::fromString($number);
    }
}
