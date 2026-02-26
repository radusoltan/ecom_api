<?php

declare(strict_types=1);

namespace App\Shipping\Application\Query\GetShippingRates;

final readonly class GetShippingRatesQuery
{
    public function __construct(
        public string $originCountry,
        public string $destinationCountry,
        public float $weightKg,
        public string $tenantId,
    ) {
    }
}
