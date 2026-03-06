<?php

declare(strict_types=1);

namespace App\Shipping\Infrastructure\Carrier;

use App\Shipping\Domain\Service\CarrierAdapterInterface;
use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\ShippingRate;
use App\Shipping\Domain\ValueObject\TrackingNumber;
use Brick\Money\Money;

/**
 * FedEx carrier adapter (sandbox/stub mode).
 *
 * In production, this would call the FedEx Rate API and Ship API.
 * Currently returns realistic sandbox rates based on weight and zone.
 */
final readonly class FedExCarrierAdapter implements CarrierAdapterInterface
{
    public function __construct(
        private string $apiKey = '',
        private bool $sandbox = true,
    ) {
    }

    public function carrierCode(): CarrierCode
    {
        return CarrierCode::fedex();
    }

    public function isEnabled(): bool
    {
        return $this->sandbox || '' !== $this->apiKey;
    }

    /** @return ShippingRate[] */
    public function calculateRates(string $originCountry, string $destinationCountry, float $weightKg): array
    {
        $isDomestic = $originCountry === $destinationCountry;
        $carrier = CarrierCode::fedex();

        $baseRate = $isDomestic ? 7.99 : 28.00;
        $weightSurcharge = $weightKg * ($isDomestic ? 0.65 : 2.80);

        return [
            ShippingRate::create(
                Money::of(round($baseRate + $weightSurcharge, 2), 'USD'),
                $carrier,
                $isDomestic ? 5 : 10,
                'FedEx Ground',
            ),
            ShippingRate::create(
                Money::of(round(($baseRate + $weightSurcharge) * 1.6, 2), 'USD'),
                $carrier,
                $isDomestic ? 2 : 5,
                'FedEx Express Saver',
            ),
            ShippingRate::create(
                Money::of(round(($baseRate + $weightSurcharge) * 2.8, 2), 'USD'),
                $carrier,
                $isDomestic ? 1 : 2,
                'FedEx Priority Overnight',
            ),
        ];
    }

    public function generateTrackingNumber(): TrackingNumber
    {
        // FedEx format: 12 or 15 digits
        $number = sprintf('%015d', random_int(100000000000, 999999999999999));

        return TrackingNumber::fromString($number);
    }
}
