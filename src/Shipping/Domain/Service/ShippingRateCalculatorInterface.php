<?php

declare(strict_types=1);

namespace App\Shipping\Domain\Service;

use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\ShippingRate;
use App\Shipping\Domain\ValueObject\TrackingNumber;

interface ShippingRateCalculatorInterface
{
    /** @return ShippingRate[] */
    public function calculateRates(string $originCountry, string $destinationCountry, float $weightKg): array;

    public function generateTrackingNumber(CarrierCode $carrier): TrackingNumber;
}
