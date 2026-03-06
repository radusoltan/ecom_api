<?php

declare(strict_types=1);

namespace App\Shipping\Domain\Service;

use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\ShippingRate;
use App\Shipping\Domain\ValueObject\TrackingNumber;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Per-carrier adapter for rate calculation and tracking number generation.
 *
 * Each carrier (UPS, FedEx, DHL, etc.) implements this interface.
 * The composite ShippingRateCalculator aggregates all adapters.
 */
#[AutoconfigureTag('app.carrier_adapter')]
interface CarrierAdapterInterface
{
    public function carrierCode(): CarrierCode;

    public function isEnabled(): bool;

    /** @return ShippingRate[] */
    public function calculateRates(string $originCountry, string $destinationCountry, float $weightKg): array;

    public function generateTrackingNumber(): TrackingNumber;
}
